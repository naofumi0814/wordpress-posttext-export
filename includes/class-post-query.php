<?php
/**
 * 投稿クエリクラス
 *
 * 検索条件に基づいてWP_Queryを構築し、投稿を取得する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPTE_Post_Query {

    /**
     * 検索条件に基づいて投稿を取得する
     *
     * @param array $params 検索条件
     * @return WP_Post[]
     */
    public function get_posts( array $params ): array {
        $query_args = $this->build_query_args( $params );
        $query = new WP_Query( $query_args );
        return $query->posts;
    }

    /**
     * 条件に一致する投稿件数を取得する
     *
     * @param array $params 検索条件
     * @return int
     */
    public function count_posts( array $params ): int {
        $query_args = $this->build_query_args( $params );
        $query_args['fields'] = 'ids';
        $query_args['no_found_rows'] = false;
        $query = new WP_Query( $query_args );
        return $query->found_posts;
    }

    /**
     * WP_Query用の引数を構築する
     *
     * @param array $params 検索条件
     * @return array
     */
    private function build_query_args( array $params ): array {
        $args = array(
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // 投稿タイプ
        if ( ! empty( $params['post_types'] ) && is_array( $params['post_types'] ) ) {
            $args['post_type'] = array_map( 'sanitize_key', $params['post_types'] );
        } else {
            $args['post_type'] = array( 'post' );
        }

        // 投稿ステータス
        if ( ! empty( $params['post_statuses'] ) && is_array( $params['post_statuses'] ) ) {
            $allowed_statuses = array( 'publish', 'draft', 'private', 'future', 'pending' );
            $args['post_status'] = array_intersect(
                array_map( 'sanitize_key', $params['post_statuses'] ),
                $allowed_statuses
            );
            if ( empty( $args['post_status'] ) ) {
                $args['post_status'] = array( 'publish' );
            }
        } else {
            $args['post_status'] = array( 'publish' );
        }

        // 期間指定
        $date_query = array();
        if ( ! empty( $params['date_from'] ) ) {
            $date_from = sanitize_text_field( $params['date_from'] );
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
                $date_query['after'] = $date_from;
                $date_query['inclusive'] = true;
            }
        }
        if ( ! empty( $params['date_to'] ) ) {
            $date_to = sanitize_text_field( $params['date_to'] );
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
                $date_query['before'] = $date_to . ' 23:59:59';
                if ( ! isset( $date_query['inclusive'] ) ) {
                    $date_query['inclusive'] = true;
                }
            }
        }
        if ( ! empty( $date_query ) ) {
            $args['date_query'] = array( $date_query );
        }

        // キーワード検索
        if ( ! empty( $params['keyword'] ) ) {
            $args['s'] = sanitize_text_field( $params['keyword'] );
        }

        // ID指定
        if ( ! empty( $params['post_ids'] ) ) {
            $ids_string = sanitize_text_field( $params['post_ids'] );
            $ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $ids_string ) ) );
            if ( ! empty( $ids ) ) {
                $args['post__in'] = $ids;
            }
        }

        // 将来的な拡張: カテゴリ、タグ、カスタムタクソノミー
        if ( ! empty( $params['category_ids'] ) && is_array( $params['category_ids'] ) ) {
            $args['category__in'] = array_map( 'absint', $params['category_ids'] );
        }

        if ( ! empty( $params['tag_ids'] ) && is_array( $params['tag_ids'] ) ) {
            $args['tag__in'] = array_map( 'absint', $params['tag_ids'] );
        }

        if ( ! empty( $params['tax_query'] ) && is_array( $params['tax_query'] ) ) {
            $args['tax_query'] = $params['tax_query'];
        }

        // ページング対応（将来拡張用）
        if ( ! empty( $params['posts_per_page'] ) ) {
            $args['posts_per_page'] = absint( $params['posts_per_page'] );
        }
        if ( ! empty( $params['paged'] ) ) {
            $args['paged'] = absint( $params['paged'] );
            $args['no_found_rows'] = false;
        }

        return $args;
    }

    /**
     * 利用可能な投稿タイプ一覧を取得する
     *
     * @return array label => name のペア
     */
    public static function get_available_post_types(): array {
        $post_types = get_post_types(
            array( 'public' => true ),
            'objects'
        );

        $result = array();
        foreach ( $post_types as $pt ) {
            // attachment（メディア）は除外
            if ( 'attachment' === $pt->name ) {
                continue;
            }
            $result[ $pt->name ] = $pt->label;
        }

        return $result;
    }
}
