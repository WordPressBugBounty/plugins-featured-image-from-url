<?php
declare(strict_types=1);

/**
 * Will bootstrap the db2 layer once it becomes needed (load classes, register hooks, etc.).
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

require_once __DIR__ . '/core/class-fifu-db2-mode.php';
require_once __DIR__ . '/core/class-fifu-db2-query-helper.php';
require_once __DIR__ . '/core/class-fifu-db2-normalizer.php';
require_once __DIR__ . '/core/class-fifu-db2-manager.php';
$fifu_post_type_utils_file = dirname(__DIR__, 2) . '/includes/meta/class-fifu-post-type-utils.php';
if (!class_exists('Fifu_Post_Type_Utils', false) && is_file($fifu_post_type_utils_file)) {
    require_once $fifu_post_type_utils_file;
}
require_once __DIR__ . '/core/class-fifu-db2-sql-helper.php';
require_once __DIR__ . '/repository/class-fifu-db2-post-repository.php';
require_once __DIR__ . '/repository/class-fifu-db2-term-repository.php';
require_once __DIR__ . '/repository/class-fifu-db2-speed-up-repository.php';
require_once __DIR__ . '/repository/class-fifu-db2-invalid-media-repository.php';
require_once __DIR__ . '/repository/class-fifu-db2-sent-repository.php';
require_once __DIR__ . '/repository/class-fifu-db2-orphan-gc-repository.php';
require_once __DIR__ . '/runtime/class-fifu-db2-write-service.php';
require_once __DIR__ . '/runtime/class-fifu-db2-legacy-write-bridge.php';
require_once __DIR__ . '/runtime/class-fifu-db2-speed-up-service.php';
require_once __DIR__ . '/runtime/class-fifu-db2-sent-service.php';
require_once __DIR__ . '/runtime/class-fifu-db2-orphan-gc-service.php';

if ( ! function_exists( 'fifu_db2_manager' ) ) {
    function fifu_db2_manager(): Fifu_Db2_Manager {
        static $instance = null;

        if ( $instance instanceof Fifu_Db2_Manager ) {
            return $instance;
        }

        global $wpdb;

        $postRepository = new Fifu_Db2_Post_Repository( $wpdb );
        $termRepository = new Fifu_Db2_Term_Repository( $wpdb );

        $instance = new Fifu_Db2_Manager( $wpdb, $postRepository, $termRepository );

        return $instance;
    }
}

if ( ! function_exists( 'fifu_db2_write_service' ) ) {
    function fifu_db2_write_service(): ?Fifu_Db2_Write_Service {
        static $instance = null;

        if ( $instance instanceof Fifu_Db2_Write_Service ) {
            return $instance;
        }

        $manager = fifu_db2_manager();
        if ( ! $manager ) {
            return null;
        }

        $instance = new Fifu_Db2_Write_Service( $manager );

        return $instance;
    }
}

if ( ! function_exists( 'fifu_db2_legacy_write_bridge' ) ) {
    function fifu_db2_legacy_write_bridge(): ?Fifu_Db2_Legacy_Write_Bridge {
        static $instance = null;

        if ( $instance instanceof Fifu_Db2_Legacy_Write_Bridge ) {
            return $instance;
        }

        $write_service = fifu_db2_write_service();
        if ( ! $write_service ) {
            return null;
        }

        $instance = new Fifu_Db2_Legacy_Write_Bridge( $write_service );

        return $instance;
    }
}

if ( ! function_exists( 'fifu_db2_speed_up_repository' ) ) {
    function fifu_db2_speed_up_repository(): Fifu_Db2_Speed_Up_Repository {
        static $instance = null;

        if ( $instance instanceof Fifu_Db2_Speed_Up_Repository ) {
            return $instance;
        }

        /**
         * phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
         */
        global $wpdb;

        $instance = new Fifu_Db2_Speed_Up_Repository( $wpdb );

        return $instance;
    }
}

if ( ! function_exists( 'fifu_db2_invalid_media_repository' ) ) {
    /**
     * Returns a singleton repository that handles fifu_invalid_media_su entries.
     *
     * @return Fifu_Db2_Invalid_Media_Repository
     */
    function fifu_db2_invalid_media_repository(): Fifu_Db2_Invalid_Media_Repository {
        static $instance = null;

        if ( $instance instanceof Fifu_Db2_Invalid_Media_Repository ) {
            return $instance;
        }

        /**
         * phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
         */
        global $wpdb;

        $instance = new Fifu_Db2_Invalid_Media_Repository( $wpdb );

        return $instance;
    }
}

if ( ! function_exists( 'fifu_db2_sent_repository' ) ) {
    /**
     * Singleton repository that handles fifu_sent table queries.
     *
     * @return Fifu_Db2_Sent_Repository
     */
    function fifu_db2_sent_repository(): Fifu_Db2_Sent_Repository {
        static $instance = null;

        if ( $instance instanceof Fifu_Db2_Sent_Repository ) {
            return $instance;
        }

        /**
         * phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
         */
        global $wpdb;

        $instance = new Fifu_Db2_Sent_Repository( $wpdb );

        return $instance;
    }
}

if ( ! function_exists( 'fifu_db2_sent_service' ) ) {
    /**
     * Returns the sent service that wraps the sent repository.
     *
     * @return Fifu_Db2_Sent_Service
     */
    function fifu_db2_sent_service(): Fifu_Db2_Sent_Service {
        static $instance = null;

        if ( $instance instanceof Fifu_Db2_Sent_Service ) {
            return $instance;
        }

        $repository = fifu_db2_sent_repository();
        $instance = new Fifu_Db2_Sent_Service( $repository );

        return $instance;
    }
}

if ( ! function_exists( 'fifu_db2_orphan_gc_repository' ) ) {
    function fifu_db2_orphan_gc_repository(): Fifu_Db2_Orphan_Gc_Repository {
        static $instance = null;
        if ( $instance instanceof Fifu_Db2_Orphan_Gc_Repository ) {
            return $instance;
        }
        global $wpdb;
        $instance = new Fifu_Db2_Orphan_Gc_Repository( $wpdb );
        return $instance;
    }
}

if ( ! function_exists( 'fifu_db2_orphan_gc_service' ) ) {
    function fifu_db2_orphan_gc_service(): ?Fifu_Db2_Orphan_Gc_Service {
        static $instance = null;
        if ( $instance instanceof Fifu_Db2_Orphan_Gc_Service ) {
            return $instance;
        }
        $repository = fifu_db2_orphan_gc_repository();
        if ( ! $repository instanceof Fifu_Db2_Orphan_Gc_Repository ) {
            return null;
        }
        $instance = new Fifu_Db2_Orphan_Gc_Service( $repository );
        return $instance;
    }
}

fifu_db2_orphan_gc_service();

// Cleans up db2 mappings when a post is permanently deleted.
add_action(
    'before_delete_post',
    static function ($post_id): void {
        $postId = is_numeric($post_id)
            ? (int) $post_id
            : 0;
        if ( $postId <= 0 ) {
            return;
        }

        if ( ! function_exists( 'fifu_db2_write_service' ) ) {
            return;
        }

        $write_service = fifu_db2_write_service();
        if ( ! $write_service instanceof Fifu_Db2_Write_Service ) {
            return;
        }

        $write_service->delete_post_all_mappings( $postId );
    }
);

// Cleans up db2 mappings when a term is deleted.
add_action(
    'delete_term',
    static function ( $term_id ): void {
        $term_id = (int) $term_id;
        if ( $term_id <= 0 ) {
            return;
        }

        if ( ! function_exists( 'fifu_db2_write_service' ) ) {
            return;
        }

        $write_service = fifu_db2_write_service();
        if ( ! $write_service instanceof Fifu_Db2_Write_Service ) {
            return;
        }

        $write_service->delete_term_all_mappings( $term_id );
    },
    10,
    1
);
