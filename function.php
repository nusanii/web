<?php
if (! defined('WP_DEBUG')) {
    die('Direct access forbidden.');
}

// Enqueue style dari parent theme
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
});

// Daftar Custom Post Type Media dengan dukungan thumbnail
function esaco_register_post_type_media() {
    $labels = array(
        'name'                  => _x('Media', 'Post Type General Name', 'esaco'),
        'singular_name'         => _x('Media', 'Post Type Singular Name', 'esaco'),
        'menu_name'             => __('Media', 'esaco'),
        'name_admin_bar'        => __('Media', 'esaco'),
        'featured_image'        => __('Featured Image', 'esaco'), // penting untuk thumbnail
        'set_featured_image'    => __('Set featured image', 'esaco'),
        'remove_featured_image' => __('Remove featured image', 'esaco'),
        'use_featured_image'    => __('Use as featured image', 'esaco'),
        // Label lain bisa ditambahkan sesuai kebutuhan
    );

    $args = array(
        'label'                 => __('Media', 'esaco'),
        'description'           => __('Post type for media', 'esaco'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-format-video',
        'has_archive'           => true,
        'show_in_rest'          => true,
    );
    register_post_type('media', $args);
}
add_action('init', 'esaco_register_post_type_media', 0);

// Shortcode gabungan filter kategori dan gallery media dengan ajax
function media_kategori_filter_shortcode($atts) {
    $atts = shortcode_atts(array(
        'categories'    => 'kebakaran,listrik,pesawat-uap,pesawat-angkat-angkut,pesawat-tenaga-produksi,konstruksi',
        'max_post'      => 10,
        'image_width'   => 300,
        'image_height'  => 200
    ), $atts, 'media_kategori_filter');

    $category_slugs = array_filter(array_map('trim', explode(',', $atts['categories'])));
    $categories = !empty($category_slugs) ? get_categories(['slug' => $category_slugs]) : get_categories();

    $max_post = intval($atts['max_post']);
    $img_w = intval($atts['image_width']);
    $img_h = intval($atts['image_height']);

    ob_start();
    ?>
    <div class="media-filter-buttons">
        <button class="media-filter-btn active" data-category="all">All</button>
        <?php foreach ($categories as $cat): ?>
            <button class="media-filter-btn" data-category="<?php echo esc_attr($cat->slug); ?>">
                <?php echo esc_html($cat->name); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <div id="media-gallery" class="media-gallery-container"></div>

    <style>
        .media-filter-buttons {
            margin-bottom: 15px;
        }
        .media-filter-btn {
            cursor: pointer;
            padding: 8px 15px;
            margin-right: 8px;
            border: none;
            background: #0073aa;
            color: white;
            border-radius: 3px;
            transition: background 0.3s;
            font-weight: 600; /* contoh styling button sesuai repo */
        }
        .media-filter-btn:hover,
        .media-filter-btn.active {
            background: #005177;
        }
        .media-gallery-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .media-item {
            width: <?php echo $img_w; ?>px;
        }
        .media-item img {
            width: 100%;
            height: auto;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>

    <script>
    jQuery(document).ready(function ($) {
        function loadMedia(categorySlug) {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'filter_media_posts',
                    category: categorySlug,
                    max_post: <?php echo $max_post; ?>
                },
                success: function (response) {
                    var gallery = $('#media-gallery');
                    gallery.empty();

                    if (response.success && response.data.length) {
                        $.each(response.data, function (i, post) {
                            var img = $('<img>').attr('src', post.thumbnail).attr('alt', post.title).attr('title', post.title);
                            var a = $('<a>').attr('href', post.full_image).attr('target', '_blank').addClass('media-item').append(img);
                            gallery.append(a);
                        });
                    } else {
                        gallery.html('<p>No media found.</p>');
                    }
                },
                error: function () {
                    $('#media-gallery').html('<p>Error loading media.</p>');
                }
            });
        }

        // Load all posts on initial page load
        loadMedia('all');

        // Button filter click handler
        $('.media-filter-btn').on('click', function () {
            $('.media-filter-btn').removeClass('active');
            $(this).addClass('active');
            var category = $(this).data('category');
            loadMedia(category);
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('media_kategori_filter', 'media_kategori_filter_shortcode');

// Jangan lupa buat handler ajax filter_media_posts
function filter_media_posts_callback() {
    $category = sanitize_text_field($_POST['category'] ?? '');
    $max_post = intval($_POST['max_post'] ?? 10);

    $args = [
        'post_type' => 'media',
        'posts_per_page' => $max_post,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    if ($category && $category !== 'all') {
        $args['tax_query'] = [
            [
                'taxonomy' => 'category',
                'field' => 'slug',
                'terms' => $category,
            ],
        ];
    }

    $query = new WP_Query($args);
    $items = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            if (empty($thumbnail)) {
                $thumbnail = wc_placeholder_img_src(); // fallback jika pakai WooCommerce
            }

            $items[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'thumbnail' => $thumbnail ?: '', // link url thumbnail
                'full_image' => get_the_post_thumbnail_url(get_the_ID(), 'full') ?: '',
                'permalink' => get_permalink(),
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success($items);
}
add_action('wp_ajax_filter_media_posts', 'filter_media_posts_callback');
add_action('wp_ajax_nopriv_filter_media_posts', 'filter_media_posts_callback');

// Enqueue jQuery yang dibutuhkan
function esaco_enqueue_scripts() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'esaco_enqueue_scripts');
