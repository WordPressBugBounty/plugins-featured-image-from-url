<?php

declare(strict_types=1);

/**
 * Write service for the active FIFU Free DB2 media concepts.
 */
class Fifu_Db2_Write_Service
{
    private Fifu_Db2_Manager $manager;

    public function __construct(Fifu_Db2_Manager $manager)
    {
        $this->manager = $manager;
    }

    private function ensure_runtime_schema_ready(): bool
    {
        return $this->manager->ensureRuntimeSchemaReady();
    }

    public function save_post_image(
        int $post_id,
        string $url,
        ?int $width = null,
        ?int $height = null
    ): bool {
        if (!$this->ensure_runtime_schema_ready()) {
            return false;
        }
        return $this->manager->savePostUrl(
            $post_id,
            'image',
            0,
            $url,
            $width,
            $height
        );
    }

    public function delete_post_image(
        int $post_id,
        ?int $index = null
    ): int {
        return $this->manager->deletePostMappings(
            $post_id,
            'image',
            $index
        );
    }

    public function save_term_image(
        int $term_id,
        string $url,
        ?int $width = null,
        ?int $height = null
    ): bool {
        if (!$this->ensure_runtime_schema_ready()) {
            return false;
        }
        return $this->manager->saveTermUrl(
            $term_id,
            'image',
            $url,
            $width,
            $height
        );
    }

    public function delete_term_image(int $term_id): int
    {
        return $this->manager->deleteTermMappings(
            $term_id,
            'image'
        );
    }

    public function save_post_image_alt(
        int $post_id,
        string $alt
    ): bool {
        if (!$this->ensure_runtime_schema_ready()) {
            return false;
        }
        return $this->manager->savePostAlt(
            $post_id,
            'image',
            0,
            $alt
        );
    }

    public function delete_post_image_alt(
        int $post_id,
        ?int $index = null
    ): int {
        return $this->manager->deletePostAltMappings(
            $post_id,
            'image',
            $index
        );
    }

    public function save_term_image_alt(
        int $term_id,
        string $alt
    ): bool {
        if (!$this->ensure_runtime_schema_ready()) {
            return false;
        }
        return $this->manager->saveTermAlt(
            $term_id,
            'image',
            $alt
        );
    }

    public function delete_term_image_alt(int $term_id): int
    {
        return $this->manager->deleteTermAltMappings(
            $term_id,
            'image'
        );
    }

    public function delete_post_all_mappings(int $post_id): void
    {
        $this->manager->deleteAllMappingsForDeletedPost(
            $post_id
        );
    }

    public function delete_term_all_mappings(int $term_id): void
    {
        $this->delete_term_image($term_id);
        $this->delete_term_image_alt($term_id);
    }
}
