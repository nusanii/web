<?php
if (! defined('WP_DEBUG')) {
    die('Direct access forbidden.');
}

// Enqueue style dari parent theme
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
});

/**
 * Shortcode filter kategori + gallery media dengan fitur "All" menampilkan 1 post per kategori
 * Gunakan: [media_kategori_filter categories="kebakaran,listrik,pesawat-uap,pesawat-angkat-angkut,pesawat-tenaga-produksi,konstruksi" max_post="10"]
 */
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

    // Tombol filter
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

        // Initial load all
        loadMedia('all');

        // Filter button click
        $('.media-filter-btn').on('click', function () {
            $('.media-filter-btn').removeClass('active');
            $(this).addClass('active');
            loadMedia($(this).data('category'));
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('media_kategori_filter', 'media_kategori_filter_shortcode');

/**
 * Ajax handler untuk filter media posts
 */
function ajax_filter_media_posts() {
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'all';
    $max_post = isset($_POST['max_post']) ? intval($_POST['max_post']) : 10;

    // Default categories (bisa sama dengan shortcode)
    $default_category_slugs = ['kebakaran', 'listrik', 'pesawat-uap', 'pesawat-angkat-angkut', 'pesawat-tenaga-produksi', 'konstruksi'];
    $categories = get_categories(['slug' => $default_category_slugs]);

    $result = [];

    if ($category === 'all') {
        // Ambil 1 post terbaru per kategori
        foreach ($categories as $cat) {
            $args = [
                'post_type'      => 'media',
                'posts_per_page' => 1,
                'category_name'  => $cat->slug,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];
            $query = new WP_Query($args);
            if ($query->have_posts()) {
                $query->the_post();
                $result[] = [
                    'title'      => get_the_title(),
                    'thumbnail'  => get_the_post_thumbnail_url(get_the_ID(), 'medium') ?: esc_url(get_template_directory_uri() . '/assets/images/no-image.jpg'),
                    'full_image' => get_the_post_thumbnail_url(get_the_ID(), 'full') ?: esc_url(get_template_directory_uri() . '/assets/images/no-image.jpg'),
                    'category'   => $cat->slug,
                ];
                wp_reset_postdata();
            }
        }
    } else {
        // Ambil sesuai kategori dipilih, maksimal max_post
        $args = [
            'post_type'      => 'media',
            'posts_per_page' => $max_post,
            'category_name'  => $category,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        $query = new WP_Query($args);
        while ($query->have_posts()) {
            $query->the_post();
            $result[] = [
                'title'      => get_the_title(),
                'thumbnail'  => get_the_post_thumbnail_url(get_the_ID(), 'medium') ?: esc_url(get_template_directory_uri() . '/assets/images/no-image.jpg'),
                'full_image' => get_the_post_thumbnail_url(get_the_ID(), 'full') ?: esc_url(get_template_directory_uri() . '/assets/images/no-image.jpg'),
                'category'   => $category,
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success($result);
}
add_action('wp_ajax_filter_media_posts', 'ajax_filter_media_posts');
add_action('wp_ajax_nopriv_filter_media_posts', 'ajax_filter_media_posts');

/**
 * Shortcode menampilkan post tipe 'media' tanpa filter kategori
 * Gunakan: [media_posts posts_per_page="5"]
 */
function shortcode_media_posts($atts) {
    $atts = shortcode_atts(array(
        'posts_per_page' => 5,
    ), $atts, 'media_posts');

    $args = array(
        'post_type'      => 'media',
        'posts_per_page' => intval($atts['posts_per_page']),
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return '<p>No media posts found.</p>';
    }

    ob_start();
    echo '<div class="media-posts-shortcode">';
    while ($query->have_posts()) {
        $query->the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <a href="<?php the_permalink(); ?>" class="post-thumbnail-link">
                <?php 
                if (has_post_thumbnail()) {
                    the_post_thumbnail('medium');
                } else {
                    echo '<img src="' . esc_url(get_template_directory_uri() . '/assets/images/no-image.jpg') . '" alt="No image">';
                }
                ?>
            </a>
            <h2 class="entry-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h2>
            <div class="entry-excerpt"><?php the_excerpt(); ?></div>
        </article>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('media_posts', 'shortcode_media_posts');

/**
 * Shortcode menampilkan post tipe 'news/artikel'
 * Gunakan: [news_posts posts_per_page="5"]
 */
function shortcode_news_posts($atts) {
    $atts = shortcode_atts(array(
        'posts_per_page' => 5,
    ), $atts, 'news_posts');

    $args = array(
        'post_type'      => 'news',
        'posts_per_page' => intval($atts['posts_per_page']),
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return '<p>No news posts found.</p>';
    }

    ob_start();
    echo '<div class="news-posts-shortcode">';
    while ($query->have_posts()) {
        $query->the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <a href="<?php the_permalink(); ?>" class="post-thumbnail-link">
                <?php 
                if (has_post_thumbnail()) {
                    the_post_thumbnail('medium');
                } else {
                    echo '<img src="' . esc_url(get_template_directory_uri() . '/assets/images/no-image.jpg') . '" alt="No image">';
                }
                ?>
            </a>
            <h2 class="entry-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h2>
            <div class="entry-excerpt"><?php the_excerpt(); ?></div>
        </article>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('news_posts', 'shortcode_news_posts');
