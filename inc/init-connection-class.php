<?php

namespace Elastic\Inc;

use Elasticsearch\ClientBuilder;
use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use PurpleDsHub\Inc\Utilities\General_Utilities;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;

if ( ! class_exists( 'Init_Connection' ) ) {
	class Init_Connection {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'init-connection';

		/**
		 */
		private $connection;

		/**
		 * Init_Connection constructor.
		 */
		public function __construct() {
			if ( is_null( $this->connection ) ) {
				// connect to Elasticsearch.
				$client = ClientBuilder::create()->build();

				// select a database.
				$this->connection = $client;
				debug_log( 'new connection' );
			}
			$update_post = new Elastic_Index( $this->connection );
			$update_post->init_hooks();
		}
	}
}

