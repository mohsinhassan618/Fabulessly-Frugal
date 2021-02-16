<?php


class fabulesslyFrugal
{


    /**
     * The unique instance of the plugin.
     * @var singleton object
     */
    private static $instance;

    // Plugin vars
    public $pluginUri;
    public $pluginDir;
    public $pluginName = 'fabulessly-frugal';
    public $pluginSlug = '';


    public $bookApiUrl = 'https://openlibrary.org/isbn/';
    public $authorApiUrl = 'https://openlibrary.org/';
    public $format = '.json';
    public $bookTemplate = '/tempaltes/single-book.php';
    public $bookPostTypeSlug = 'books/mirror';


    /**
     * Gets an instance of our plugin.
     * @return object
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Constructor
     */
    private function __construct()
    {
    }


    public function init()
    {
        //
        $this->pluginDir = plugin_dir_path(__FILE__);
        $this->pluginUri = plugin_dir_url(__FILE__);

        add_action('init', [$this, 'bookPostType'], 0);
        add_action('add_meta_boxes', [$this, 'FFRegisterMetaBoxes']);
        add_action('save_post_book', [$this, 'saveBookMeta'], 3, 3);
        add_filter('single_template', [$this, 'bookLoadTemplate'], -1);
        add_action('wp_enqueue_scripts', [$this, 'wpEnqueueTemplatesScripts']);
        add_action('rest_api_init', [$this, 'registerRestRoute']);
        //http://yourdomain.com/wp-json/fabulessly-frugal/v1/books/bookID

        register_activation_hook(__DIR__.'/index.php', [$this, 'fabulesslyFrugalActivationSetup']);
    }

    function fabulesslyFrugalActivationSetup()
    {
        $this->bookPostType();
        flush_rewrite_rules();
    }

    // Register Book Post Type
    function bookPostType()
    {

        $labels = array(
            'name' => _x('Books', 'post type general name', $this->pluginName),
            'singular_name' => _x('Book', 'post type singular name', $this->pluginName),
            'menu_name' => _x('Books', 'admin menu', $this->pluginName),
            'name_admin_bar' => _x('Book', 'add new on admin bar', $this->pluginName),
            'add_new' => _x('Add New', 'book', $this->pluginName),
            'add_new_item' => __('Add New Book', $this->pluginName),
            'new_item' => __('New Book', $this->pluginName),
            'edit_item' => __('Edit Book', $this->pluginName),
            'view_item' => __('View Book', $this->pluginName),
            'all_items' => __('All Books', $this->pluginName),
            'search_items' => __('Search Books', $this->pluginName),
            'parent_item_colon' => __('Parent Books:', $this->pluginName),
            'not_found' => __('No books found.', $this->pluginName),
            'not_found_in_trash' => __('No books found in Trash.', $this->pluginName)
        );

        $args = array(
            'labels' => $labels,
            'description' => __('Description.', $this->pluginName),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => $this->bookPostTypeSlug),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'show_in_rest' => false,
            'rest_base' => 'books',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'supports' => array('title')
        );
        register_post_type('book', $args);

    }

    function FFRegisterMetaBoxes()
    {
        add_meta_box('book-meta-box', __('Book Details', $this->pluginName), [$this, 'FFMetaBoxCallBack'], 'book');
    }


    /**
     * Meta box display callback.
     *
     * @param WP_Post $post Current post object.
     */
    function FFMetaBoxCallBack($post)
    {

        $isbn = get_post_meta($post->ID, 'isbn', true);
        $isbn = !empty($isbn) ? $isbn : '';
        wp_nonce_field('book_meta_box', 'book_meta_nonce'); ?>
        <div class="wrap">
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th>ISBN Number:</th>
                    <td><input type="text" name="isbn" value="<?php echo trim($isbn); ?>"></td>
                </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    function saveBookMeta($postId)
    {
        if (wp_verify_nonce($_POST['book_meta_nonce'], 'book_meta_box')) {
            if (wp_is_post_revision($postId)) {
                return;
            }
            if (isset($_POST['isbn']) && !empty($_POST['isbn'])) {

                $isbn = sanitize_text_field($_POST['isbn']);
                update_post_meta($postId, 'isbn', $isbn);
                $previous_data = get_post_meta($postId, 'book_details', true);
                if ($previous_data == false && empty($previous_data)) {
                    $data = $this->getBookDetails($isbn);
                    if ($data != false && is_array($data)) {
                        update_post_meta($postId, 'book_details', $data);
                        update_post_meta($postId, 'isbn', $isbn);
                    }
                }
            }
        }
    }


    function getBookDetails($isbn)
    {

        $url = $this->bookApiUrl . $isbn . $this->format;
        try {
            $response = wp_remote_get($url, array('timeout' => 20));
            $responseCode = wp_remote_retrieve_response_code($response);

            if (!is_wp_error($response) && $responseCode == 200) {
                $body = wp_remote_retrieve_body($response);
                $dataRawBook = (array)json_decode($body);
                $authorArray = [];
                foreach ($dataRawBook['authors'] as $author) {
                    $url = $this->authorApiUrl . $author->key . $this->format;
                    $response = wp_remote_get($url, array('timeout' => 20));
                    $responseCode = wp_remote_retrieve_response_code($response);

                    if (!is_wp_error($response) && $responseCode == 200) {
                        $body = wp_remote_retrieve_body($response);
                        $dataRawAuthor = (array)json_decode($body);
                        if (isset($dataRawAuthor['name'])) {
                            $authorArray[] = $dataRawAuthor['name'];
                        }
                    }
                }
                $bookData = array(
                    'authors' => $authorArray,
                    'publish_date' => $dataRawBook['publish_date'],
                    'publishers' => $dataRawBook['publishers'],
                    'number_of_pages' => $dataRawBook['number_of_pages'],
                );
                return $bookData;
            } else {
                wp_die('No Book Found: ' . $url);
            }

        } catch (Exception $ex) {
            wp_die('Opps! Something Went Wrong');
        }
    }

    function bookLoadTemplate($single)
    {
        global $post;
        if ($post->post_type == 'book') {
            if (file_exists($this->pluginDir . $this->bookTemplate)) {
                return $this->pluginDir . $this->bookTemplate;
            }
        }
        return $single;
    }

    function wpEnqueueTemplatesScripts()
    {
        global $post;
        if (is_single() && $post->post_type == 'book') {
            wp_register_style('bootstrap-book', $this->pluginUri . '/assets/bootstrap/css/bootstrap.min.css', '', '');
            wp_register_script('bootstrap-book-js', $this->pluginUri . '/assets/bootstrap/js/bootstrap.bundle.min.js', 'jquery', '', true);

            wp_enqueue_style('bootstrap-book');
            wp_enqueue_script('bootstrap-book-js');
        }
    }

    public function registerRestRoute()
    {
        register_rest_route($this->pluginName . '/v1', '/books/(?P<id>[\d]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'booksRestRouteCallBack'],
        ]);
    }

    public function booksRestRouteCallBack($data = null)
    {
        $singleId = isset($data['id']) ? (int)$data['id'] : null;
        $book = get_post($singleId);
        if (!empty($book) && $book->post_type == 'book') {
            $meta = get_post_meta($singleId, 'book_details', true);
            $isbn = get_post_meta($singleId, 'isbn', true);
            $response = array(
                'isbn' => $isbn,
                'meta' => $meta
            );
            wp_send_json_success($response, 200);

        } else {
            wp_send_json_error(
                ['message' => 'Unable to find the post.']
                , 404);
        }


    }


}


