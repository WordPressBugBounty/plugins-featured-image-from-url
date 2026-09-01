<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (
    !class_exists(
        'Fifu_Manual_Metaout_Busy_Exception',
        false
    )
) {
    class Fifu_Manual_Metaout_Busy_Exception
        extends RuntimeException {}
}

final class Fifu_Manual_Metaout_Service
{
    private const STATE_OPTION =
        'fifu_manual_metaout_state';

    private const TOTAL_OPTION =
        'fifu_manual_metaout_total';

    private const FINALIZED_OPTION =
        'fifu_manual_metaout_finalized';

    private const STATES = [
        'idle',
        'running',
        'complete',
    ];

    public static function start(): array
    {
        self::acquire_processing_lock();

        try {
            $repository =
                new Fifu_Metadata_Queue_Repository();

            $rows =
                $repository->get_meta_out();

            $remaining =
                self::remaining();

            $state =
                self::state();

            $finalized =
                self::is_finalized();

            if (
                $state !== 'running'
                && get_option(
                    'fifu_manual_metain_state',
                    'idle'
                ) === 'running'
            ) {
                throw new Fifu_Manual_Metaout_Busy_Exception(
                    'Manual metadata generation is busy.'
                );
            }

            self::disable_metadata_cron_if_enabled();

            if (
                $state === 'running'
                && $remaining === 0
            ) {
                self::finalize_once();

                return self::build_status(
                    'complete',
                    0,
                    true
                );
            }

            if (
                $state === 'complete'
                && !$finalized
                && $remaining === 0
            ) {
                self::finalize_once();

                return self::build_status(
                    'complete',
                    0,
                    true
                );
            }

            if (
                $state === 'running'
                && $remaining > 0
            ) {
                self::repair_total(
                    $remaining
                );

                return self::build_status(
                    self::state(),
                    $remaining,
                    self::is_finalized()
                );
            }

            if (
                $state !== 'running'
                && $rows !== []
            ) {
                self::persist(
                    self::TOTAL_OPTION,
                    $remaining
                );

                self::persist(
                    self::FINALIZED_OPTION,
                    false
                );

                self::persist(
                    self::STATE_OPTION,
                    'running'
                );

                return self::build_status(
                    'running',
                    $remaining,
                    false
                );
            }

            Fifu_Meta_Maintenance_Controller::
                clear_meta_in();

            (
                new Fifu_Local_Media_Cleanup(
                    null,
                    (int)
                        Fifu_Options_Utils::
                            get_author()
                )
            )->delete_garbage();

            Fifu_Metadata_Import_Service::
                prepare_meta_out_queue(
                    '',
                    false
                );

            $remaining =
                self::remaining();

            self::persist(
                self::TOTAL_OPTION,
                $remaining
            );

            self::persist(
                self::FINALIZED_OPTION,
                false
            );

            if ($remaining === 0) {
                self::finalize_once();
            } else {
                self::persist(
                    self::STATE_OPTION,
                    'running'
                );
            }

            return self::build_status(
                self::state(),
                self::remaining(),
                self::is_finalized()
            );
        } finally {
            self::release_processing_lock();
        }
    }

    public static function status(): array
    {
        $state =
            self::state();

        $remaining =
            self::remaining();

        $finalized =
            self::is_finalized();

        if (
            $remaining === 0
            && in_array(
                $state,
                [
                    'running',
                    'complete',
                ],
                true
            )
            && !$finalized
        ) {
            self::acquire_processing_lock();

            try {
                $state =
                    self::state();

                $remaining =
                    self::remaining();

                $finalized =
                    self::is_finalized();

                if (
                    $remaining === 0
                    && in_array(
                        $state,
                        [
                            'running',
                            'complete',
                        ],
                        true
                    )
                    && !$finalized
                ) {
                    self::finalize_once();

                    $state =
                        'complete';

                    $finalized =
                        true;
                }

                return self::build_status(
                    $state,
                    $remaining,
                    $finalized
                );
            } finally {
                self::release_processing_lock();
            }
        }

        return self::build_status(
            $state,
            $remaining,
            $finalized
        );
    }

    public static function process_next_batch(): array
    {
        if (
            self::state()
            !== 'running'
        ) {
            return self::status();
        }

        self::acquire_processing_lock();

        try {
            $state =
                self::state();

            if ($state !== 'running') {
                return self::build_status(
                    $state,
                    self::remaining(),
                    self::is_finalized()
                );
            }

            $repository =
                new Fifu_Metadata_Queue_Repository();

            $rows =
                $repository->get_meta_out();

            $row =
                $rows[0]
                ?? null;

            $id =
                self::row_id(
                    $row
                );

            if ($id === null) {
                self::finalize_once();

                return self::build_status(
                    self::state(),
                    self::remaining(),
                    self::is_finalized()
                );
            }

            $type =
                $repository
                    ->get_meta_out_type(
                        $id
                    );

            if ($type === 'att') {
                Fifu_Metadata_Import_Service::
                    process_att_meta_out_row(
                        $id
                    );
            } elseif ($type === 'term') {
                Fifu_Metadata_Import_Service::
                    process_term_meta_out_row(
                        $id
                    );
            } elseif ($type === 'woo') {
                $repository
                    ->delete_meta_out_row(
                        $id
                    );
            } else {
                throw new RuntimeException(
                    'Unknown manual metaout queue type.'
                );
            }

            if (
                self::remaining()
                === 0
            ) {
                self::finalize_once();
            }

            return self::build_status(
                self::state(),
                self::remaining(),
                self::is_finalized()
            );
        } finally {
            self::release_processing_lock();
        }
    }

    private static function build_status(
        string $state,
        int $remaining,
        bool $finalized
    ): array {
        $total =
            max(
                0,
                (int)
                    get_option(
                        self::TOTAL_OPTION,
                        0
                    )
            );

        if (
            $state !== 'idle'
            && $remaining > $total
        ) {
            $total =
                $remaining;

            self::persist(
                self::TOTAL_OPTION,
                $total
            );
        }

        $processed =
            max(
                0,
                $total
                    - $remaining
            );

        $done =
            $state === 'complete'
            && $remaining === 0
            && $finalized;

        $percent =
            $done
                ? 100
                : (
                    $total <= 0
                        ? 0
                        : min(
                            99,
                            max(
                                0,
                                (int)
                                    floor(
                                        $processed
                                        * 100
                                        / $total
                                    )
                            )
                        )
                );

        $nextId = null;

        foreach (
            (
                new Fifu_Metadata_Queue_Repository()
            )->get_meta_out()
            as $row
        ) {
            $nextId =
                self::row_id(
                    $row
                );

            if ($nextId !== null) {
                break;
            }
        }

        return [
            'success' => true,
            'state' => $state,
            'running' =>
                $state === 'running',
            'done' => $done,
            'total' => $total,
            'remaining' =>
                $remaining,
            'processed' =>
                $processed,
            'percent' => $percent,
            'next_id' => $nextId,
        ];
    }

    private static function processing_lock_name(): string
    {
        global $wpdb;

        return
            'fifu_metadata_'
            . substr(
                md5(
                    DB_NAME
                    . ':'
                    . $wpdb->prefix
                ),
                0,
                32
            );
    }

    private static function acquire_processing_lock(): void
    {
        global $wpdb;

        $name =
            self::processing_lock_name();

        $result =
            $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT GET_LOCK(%s, 0)',
                    $name
                )
            );

        if (
            $result === 1
            || $result === '1'
        ) {
            return;
        }

        if (
            $result === 0
            || $result === '0'
        ) {
            throw new Fifu_Manual_Metaout_Busy_Exception(
                'Manual metadata cleanup is busy.'
            );
        }

        throw new RuntimeException(
            'Unable to acquire manual metadata lock.'
        );
    }

    private static function release_processing_lock(): void
    {
        global $wpdb;

        $name =
            self::processing_lock_name();

        $wpdb->get_var(
            $wpdb->prepare(
                'SELECT RELEASE_LOCK(%s)',
                $name
            )
        );
    }

    private static function disable_metadata_cron_if_enabled(): void
    {
        if (
            !Fifu_Options_Utils::is_on(
                'fifu_cron_metadata'
            )
        ) {
            return;
        }

        update_option(
            'fifu_cron_metadata',
            'toggleoff',
            'no'
        );

        $request =
            new \WP_REST_Request();

        $request->set_param(
            'toggle',
            'fifu_toggle_cron_metadata'
        );

        Fifu_Rest_Cron_Controller::
            cron_delete(
                $request
            );
    }

    private static function finalize_once(): void
    {
        if (
            self::is_finalized()
        ) {
            if (
                self::state()
                !== 'complete'
            ) {
                self::persist(
                    self::STATE_OPTION,
                    'complete'
                );
            }

            return;
        }

        update_option(
            'fifu_fake',
            'toggleoff',
            'no'
        );

        update_option(
            'fifu_data_clean',
            'toggleoff',
            'no'
        );

        Fifu_Options_Utils::
            set_author();

        if (
            method_exists(
                'Fifu_Options_Utils',
                'maybe_upgrade_author_after_metadata_cleanup'
            )
        ) {
            Fifu_Options_Utils::
                maybe_upgrade_author_after_metadata_cleanup();
        }

        self::persist(
            self::FINALIZED_OPTION,
            true
        );

        self::persist(
            self::STATE_OPTION,
            'complete'
        );
    }

    private static function state(): string
    {
        $state =
            get_option(
                self::STATE_OPTION,
                'idle'
            );

        return in_array(
            $state,
            self::STATES,
            true
        )
            ? $state
            : 'idle';
    }

    private static function is_finalized(): bool
    {
        return filter_var(
            get_option(
                self::FINALIZED_OPTION,
                false
            ),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private static function remaining(): int
    {
        return max(
            0,
            (int)
                (
                    new Fifu_Migration_Stats()
                )->count_meta_out_operations()
        );
    }

    private static function repair_total(
        int $remaining
    ): void {
        $total =
            max(
                0,
                (int)
                    get_option(
                        self::TOTAL_OPTION,
                        0
                    )
            );

        if (
            $remaining > $total
        ) {
            self::persist(
                self::TOTAL_OPTION,
                $remaining
            );
        }
    }

    private static function persist(
        string $name,
        $value
    ): void {
        update_option(
            $name,
            $value,
            false
        );
    }

    private static function row_id(
        $row
    ): ?int {
        $value =
            is_object(
                $row
            )
                ? (
                    $row->post_id
                    ?? null
                )
                : (
                    is_array(
                        $row
                    )
                        ? (
                            $row['post_id']
                            ?? null
                        )
                        : null
                );

        return
            is_numeric(
                $value
            )
            && (int) $value > 0
                ? (int) $value
                : null;
    }
}
