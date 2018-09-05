// Запускаем наш контроллер и регистрируем маршруты
add_action( 'rest_api_init', 'prefix_register_my_rest_routes' );
function prefix_register_my_rest_routes() {
	$controller = new My_REST_Posts_Controller();
	$controller->register_routes();
}

class My_REST_Posts_Controller extends WP_REST_Controller {

	## Инициализация, во время которой указываем namespace и resource_name
	function __construct() {
		$this->namespace     = 'my-namespace/v1';
		$this->resource_name = 'posts';
	}

	## Регистрация маршрутов
	function register_routes() {

		register_rest_route( $this->namespace, "/$this->resource_name", array(
			// конечная точка для чтения коллекции ресурсов
			array(
				'methods'   => 'GET',
				'callback'  => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
			// схема ресурса
			'schema' => array( $this, 'get_item_schema' ),
		) );

		register_rest_route( $this->namespace, "/$this->resource_name/(?P<id>[\d]+)", array(
			// конечная точка для чтения отдельного ресурса
			array(
				'methods'   => 'GET',
				'callback'  => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			),
			// схема ресурса
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	## Проверяет доступ к чтению ресурсов.
	function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'read' ) )
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the post resource.' ), array( 'status' => $this->error_status_code() ) );

		return true;
	}

	/**
	 * Получает последние посты и отдает их в виде rest ответа.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	function get_items( $request ) {
		$data = array();

		$posts = get_posts( array(
			'post_per_page' => 5,
		) );

		if ( empty( $posts ) )
			return $data;

		foreach ( $posts as $post ) {
			$response = $this->prepare_item_for_response( $post, $request );
			$data[] = $this->prepare_response_for_collection( $response );
		}

		return $data;
	}

	## Проверка права доступа.
	function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check();
	}

	/**
	 * Получает отдельный ресурс.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	function get_item( $request ) {
		$id = (int) $request['id'];
		$post = get_post( $id );

		if ( empty( $post ) )
			return array();

		return prepare_item_for_response( $post, $request );
	}

	/**
	 * Собирает данные ресурса в соответствии со схемой ресурса.
	 *
	 * @param WP_Post         $post    Объект ресурса, из которого будут взяты оригинальные данные.
	 * @param WP_REST_Request $request Текущий запрос.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	function prepare_item_for_response( $post, $request ) {
		$post_data = array();

		$schema = $this->get_item_schema();

		// We are also renaming the fields to more understandable names.
		if ( isset( $schema['properties']['id'] ) )
			$post_data['id'] = (int) $post->ID;

		if ( isset( $schema['properties']['content'] ) )
			$post_data['content'] = apply_filters( 'the_content', $post->post_content, $post );

		return $post_data;
	}

	## Подготавливает ответ отдельного ресурса для добавления его в коллекцию ресурсов.
	function prepare_response_for_collection( $response ) {
		if ( ! ( $response instanceof WP_REST_Response ) ) {
			return $response;
		}

		$data = (array) $response->get_data();
		$server = rest_get_server();

		if ( method_exists( $server, 'get_compact_response_links' ) ) {
			$links = call_user_func( array( $server, 'get_compact_response_links' ), $response );
		} else {
			$links = call_user_func( array( $server, 'get_response_links' ), $response );
		}

		if ( ! empty( $links ) ) {
			$data['_links'] = $links;
		}

		return $data;
	}

	## Схема ресурса.
	function get_item_schema() {
		$schema = array(
			// // показывает какую версию схемы мы используем - это draft 4
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			// определяет ресурс который описывает схема
			'title'                => 'post',
			'type'                 => 'object',
			// в JSON схеме нужно указывать свойства в атрибуете 'properties'.
			'properties'           => array(
				'id' => array(
					'description'  => esc_html__( 'Unique identifier for the object.', 'my-textdomain' ),
					'type'         => 'integer',
					'context'      => array( 'view', 'edit', 'embed' ),
					'readonly'     => true,
				),
				'content' => array(
					'description'  => esc_html__( 'The content for the object.', 'my-textdomain' ),
					'type'         => 'string',
				),
			),
		);

		return $schema;
	}

	## Устанавливает HTTP статус код для авторизации.
	function error_status_code() {
		return is_user_logged_in() ? 403 : 401;
	}

}
