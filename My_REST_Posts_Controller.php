// Запускаем наш контроллер и регистрируем маршруты
add_action( 'rest_api_init', 'prefix_register_my_rest_routes' );
function prefix_register_my_rest_routes() {
	$controller = new My_REST_Posts_Controller();
	$controller->register_routes();
}

class My_REST_Posts_Controller extends WP_REST_Controller {

	function __construct(){
		$this->namespace = 'my-namespace/v1';
		$this->rest_base = 'posts';
	}

	function register_routes(){

		register_rest_route( $this->namespace, "/$this->rest_base", [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
			],
			'schema' => [ $this, 'get_item_schema' ],
		] );

		register_rest_route( $this->namespace, "/$this->rest_base/(?P<id>[\w]+)", [
			[
				'methods'   => 'GET',
				'callback'  => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
			],
			'schema' => [ $this, 'get_item_schema' ],
		] );
	}

	function get_items_permissions_check( $request ){
		if ( ! current_user_can( 'read' ) )
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the post resource.' ), [ 'status' => $this->error_status_code() ] );

		return true;
	}

	/**
	 * Получает последние посты и отдает их в виде rest ответа.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 *
	 * @return WP_Error|array
	 */
	function get_items( $request ){
		$data = [];

		$posts = get_posts( [
			'post_per_page' => 5,
		] );

		if ( empty( $posts ) )
			return $data;

		foreach( $posts as $post ){
			$response = $this->prepare_item_for_response( $post, $request );
			$data[] = $this->prepare_response_for_collection( $response );
		}

		return $data;
	}

	## Проверка права доступа.
	function get_item_permissions_check( $request ){
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Получает отдельный ресурс.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 *
	 * @return array
	 */
	function get_item( $request ){
		$id = (int) $request['id'];
		$post = get_post( $id );

		if( ! $post )
			return array();

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Собирает данные ресурса в соответствии со схемой ресурса.
	 *
	 * @param WP_Post         $post    Объект ресурса, из которого будут взяты оригинальные данные.
	 * @param WP_REST_Request $request Текущий запрос.
	 *
	 * @return array
	 */
	function prepare_item_for_response( $post, $request ){

		$post_data = [];

		$schema = $this->get_item_schema();

		// We are also renaming the fields to more understandable names.
		if ( isset( $schema['properties']['id'] ) )
			$post_data['id'] = (int) $post->ID;

		if ( isset( $schema['properties']['content'] ) )
			$post_data['content'] = apply_filters( 'the_content', $post->post_content, $post );

		return $post_data;
	}

	/**
	 * Подготавливает ответ отдельного ресурса для добавления его в коллекцию ресурсов.
	 *
	 * @param WP_REST_Response $response Response object.
	 *                                   
	 * @return array|mixed Response data, ready for insertion into collection data.
	 */
	function prepare_response_for_collection( $response ){

		if ( ! ( $response instanceof WP_REST_Response ) ){
			return $response;
		}

		$data = (array) $response->get_data();
		$server = rest_get_server();

		if ( method_exists( $server, 'get_compact_response_links' ) ){
			$links = call_user_func( [ $server, 'get_compact_response_links' ], $response );
		}
		else {
			$links = call_user_func( [ $server, 'get_response_links' ], $response );
		}

		if ( ! empty( $links ) ){
			$data['_links'] = $links;
		}

		return $data;
	}

	## Схема ресурса.
	function get_item_schema(){
		$schema = [
			// показывает какую версию схемы мы используем - это draft 4
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			// определяет ресурс который описывает схема
			'title'      => 'vehicle',
			'type'       => 'object',
			// в JSON схеме нужно указывать свойства в атрибуете 'properties'.
			'properties' => [
				'id' => [
					'description' => 'Unique identifier for the object.',
					'type'        => 'integer',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'vin' => [
					'description' => 'VIN code of vehicle.',
					'type'        => 'string',
				],
				// TODO добавить поля
				// []
			],
		];

		return $schema;
	}

	## Устанавливает HTTP статус код для авторизации.
	function error_status_code(){
		return is_user_logged_in() ? 403 : 401;
	}

}
