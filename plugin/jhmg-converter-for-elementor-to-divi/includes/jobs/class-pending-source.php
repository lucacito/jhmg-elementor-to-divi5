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
}
