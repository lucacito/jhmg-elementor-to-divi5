<?php
/**
 * "Which Elementor posts are still waiting to be converted?"
 *
 * An interface so JobService can be unit-tested; PendingQuery is the SQL
 * implementation and is verified against a real database.
 */

namespace ElementorDivi5Converter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface PendingSource {

    /** @param string[] $post_types */
    public function count( array $post_types ): int;

    /**
     * Keyset-paginated, ascending by ID: cheap at any depth, unlike OFFSET.
     *
     * @param string[] $post_types
     * @return array[] Rows with ID, post_type, post_title.
     */
    public function page( array $post_types, int $after_id, int $limit ): array;

    /** Offset pagination for the screen. @return array[] ID, post_type, post_title, post_status, post_modified. */
    public function list( array $post_types, int $limit, int $offset, string $search = '' ): array;

    /** Pending posts matching a title search (empty = all), for pagination. */
    public function count_matching( array $post_types, string $search ): int;

    /** @return array<string,int> Pending posts per post type. */
    public function counts_by_type( array $post_types ): array;

    /** Posts carrying the converted flag, however it got there. */
    public function converted_count(): int;
}
