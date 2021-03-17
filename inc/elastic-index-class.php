<?php

namespace Elastic\Inc;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use PurpleDsHub\Inc\Utilities\Torque_Urls;
use const PurpleDsHub\Inc\Api\PURPLE_IN_ISSUES;

if ( ! class_exists( 'Elastic_Index' ) ) {
	class Elastic_Index implements Hooks_Interface {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'elastic-index';

		/**
		 * Connection to Elasticsearch db.
		 *
		 * @param Client $connection connection to Elasticsearch.
		 */
		private $connection;

		/**
		 * Elastic_Index constructor.
		 *
		 * @param Client $connection connection to Elasticsearch.
		 */
		public function __construct( $connection ) {
			$this->connection = $connection;
		}

		/**
		 * Initialize all hooks.
		 */
		public function init_hooks() {
			add_action( 'save_post', array( $this, 'save_in_db' ), 10, 3 );
			/*add_action( 'wp_loaded', array( $this, 'update_all_posts' ) );*/
		}

		/**
		 * Update all posts in db.
		 */
		public function update_all_posts() {
			$args      = array(
				'numberposts' => -1,
				'post_status' => 'any',
				'post_type'   => get_post_types( '', 'names' ),
			);
			$all_posts = get_posts( $args );
			foreach ( $all_posts as $single_post ) {
				$this->save_in_db( $single_post->ID, $single_post, null );
			}
		}

		/**
		 * Filter out null blocks.
		 *
		 * @param array $block block that gets filtered.
		 * @return bool
		 */
		private function filter_blocks( $block ) {
			return $block['blockName'] !== null;
		}


		/**
		 * Save in Elastisearch db.
		 *
		 * @param int      $post_id id of current post.
		 * @param \WP_Post $post current post.
		 * @param bool     $update Whether this is an existing post being updated.
		 */
		public function save_in_db( $post_id, \WP_Post $post, $update ) {
			libxml_use_internal_errors( true );
			$blocks          = parse_blocks( $post->post_content );
			$blocks_filtered = array_filter( $blocks, array( $this, 'filter_blocks' ) );
			$post_categories = wp_get_post_categories( $post_id );
			$post_meta       = get_post_meta( $post_id, '', true );
			$custom_fields   = array();
			$issues          = get_post_meta( $post_id, PURPLE_IN_ISSUES, true ) ?: array();
			$issues          = array_map(
				function ( $issue_id ) {
					return get_post( $issue_id );
				},
				$issues
			);
			$articles        = get_post_meta( $post_id, 'purple_issue_articles', true );
			$articles        = array_map(
				function ( $article_id ) {
					$post                      = get_post( $article_id );
					$post->{'permalink'}       = get_permalink( $article_id );
					$post->{'author_name'}     = get_the_author_meta( 'display_name', $post->post_author );
					$post->{'article_options'} = get_post_meta( $article_id, 'purple_content_options', true );

					return $post;
				},
				$articles
			);
			foreach ( $post_meta as $meta_key => $meta_value ) {
				if ( Torque_Urls::starts_with( $meta_key, 'purple_custom_meta_' ) ) {
					$stripped_key = str_replace( 'purple_custom_meta_', '', $meta_key );
					array_push(
						$custom_fields,
						array(
							'field' => $stripped_key,
							'value' => $meta_value[0],
						)
					);
				}
			}
			$author_id = get_post_field( 'post_author', $post_id );
			$term_list = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'all' ) );
			$term_ids  = array();
			foreach ( $term_list as $term ) {
				array_push(
					$term_ids,
					$term->term_id
				);
			}
			$params = array(
				'index' => 'wordpress_post',
				'id'    => $post_id,
				'body'  => array(
					'id'                  => $post_id,
					'postContent'         => $blocks_filtered,
					'categories'          => $post_categories,
					'custom_fields'       => $custom_fields,
					'author'              => intval( $author_id ),
					'tags'                => $term_ids,
					'post_title'          => $post->post_title,
					'post_status'         => $post->post_status,
					'post_parent'         => $post->post_parent,
					'comment_status'      => $post->comment_status,
					'post_name'           => $post->post_name,
					'post_modified'       => $post->post_modified,
					'post_modified_gmt'   => $post->post_modified_gmt,
					'guid'                => $post->guid,
					'post_type'           => $post->post_type,
					'purpleIssue'         => $issues[0]->ID,
					'purpleIssueArticles' => array_column( $articles, 'ID' ),
				),
			);
			$this->connection->index( $params );
			$categories = get_categories();
			foreach ( $categories as $category ) {
				$params = array(
					'index' => 'wordpress_category',
					'id'    => $category->term_id,
					'body'  => array(
						'id'   => $category->term_id,
						'name' => $category->name,
					),
				);
				$this->connection->index( $params );
			}
			$users = get_users();
			foreach ( $users as $user ) {
				$params = array(
					'index' => 'wordpress_user',
					'id'    => $user->ID,
					'body'  => array(
						'id'           => $user->ID,
						'login'        => $user->data->user_login,
						'display_name' => $user->data->display_name,
						'email'        => $user->data->user_email,
					),
				);
				$this->connection->index( $params );
			}
			$tags = get_tags();
			foreach ( $tags as $tag ) {
				$params = array(
					'index' => 'wordpress_tag',
					'id'    => $tag->term_id,
					'body'  => array(
						'id'   => $tag->term_id,
						'name' => $tag->name,
						'slug' => $tag->slug,
					),
				);
				$this->connection->index( $params );
			}
		}
	}
}

