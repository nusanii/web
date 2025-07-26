<?php

if (! defined('WP_DEBUG')) {
    die('Direct access forbidden.');
}

// Enqueue style parent theme
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
});

/**
 * Shortcode menampilkan post 'media' berdasarkan kategori tertentu
 * jika categories = "slug-kategori1,slug-kategori2", maka filter post sesuai kategori
 * Jika categories = "all", tampilkan 1 post thumbnail per kategori
 *
 * Gunakan:
 * [media_kategori_filter categories="all" max_post="1"]
 * atau
 * [media_kategori_filter categories="kebakaran,listrik,pesawat-uap,ketinggian" max_post="5"]
 */
function media_kategori_filter_shortcode($atts) {
    $atts = shortcode_atts(array(
        'categories' => 'kebakaran,listrik,pesawat-uap,pesawat-angkat-angkut,pesawat-tenaga-produksi,konstruksi,ketinggian', // default kategori termasuk ketinggian
        'max_post' => 10,     // max post yang ditampilkan, default 10
        'image_width' => 300, // lebar gambar thumbnail
        'image_height' => 220 // tinggi gambar thumbnail
    ), $atts, 'media_kategori_filter');

    $category_slugs = array_filter(array_map('trim', explode(',', $atts['categories'])));
    $max_post = intval($atts['max_post']);

    // Jika categories = 'all', maka ambil 1 post terbaru per kategori
    if (in_array('all', array_map('strtolower', $category_slugs))) {
        // Tangani kasus 'all' khusus: menampilkan 1 foto per kategori
        ob_start();

        ?>
        <style>
        /* Styling tombol filter */
        .media-filter-buttons {
            margin-bottom: 20px;
            text-align: center;
        }
        .media-filter-button {
            display: inline-block;
            margin: 0 8px 12px;
            padding: 8px 18px;
            background: #0073aa;
            color: #fff;
            border-radius: 18px;
            cursor: pointer;
            user-select: none;
            transition: background-color 0.3s ease;
            font-weight: 600;
        }
        .media-filter-button.active,
        .media-filter-button:hover {
            background: #005177;
        }
        .media-kategori-filter-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: center;
        }
        .media-item-filter {
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            width: <?= intval($atts['image_width']); ?>px;
        }
        .media-item-filter:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .media-image {
            display: block;
            width: 100%;
            height: <?= intval($atts['image_height']); ?>px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 8px;
        }
        </style>

        <div class="media-kategori-filter-wrapper">
            <div class="media-filter-buttons">
                <span class="media-filter-button active" data-cat="all">All</span>
                <?php
                // Ambil objek kategori berdasarkan slug yang dipilih
                $categories_for_buttons = get_categories(array(
                    'slug' => array_filter($category_slugs, function($slug) { return strtolower($slug) !== 'all'; }),
                    'hide_empty' => true,
                ));
                foreach ($categories_for_buttons as $cat): ?>
                    <span class="media-filter-button" data-cat="<?= esc_attr($cat->slug); ?>"><?= esc_html($cat->name); ?></span>
                <?php endforeach; ?>
            </div>

            <div class="media-kategori-filter-gallery">
                <?php
                // Per kategori, ambil 1 post dengan thumbnail terbaru
                foreach ($categories_for_buttons as $cat) {
                    $args = array(
                        'post_type' => 'post',
                        'posts_per_page' => 1,
                        'category_name' => $cat->slug,
                        'meta_query' => array(
                            array(
                                'key' => '_thumbnail_id',
                                'compare' => 'EXISTS',
                            ),
                        ),
                        'orderby' => 'date',
                        'order' => 'DESC',
                    );
                    $query = new WP_Query($args);

                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();
                            $full_img_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
                            $thumb_html = get_the_post_thumbnail(get_the_ID(), array(intval($atts['image_width']), intval($atts['image_height'])), array('class' => 'media-image'));
                            ?>
                            <div class="media-item-filter" data-cats="<?= esc_attr($cat->slug); ?>">
                                <a href="<?= esc_url($full_img_url); ?>" data-lightbox="media-kategori-filter" data-title="<?= esc_attr(get_the_title()); ?>">
                                    <?= $thumb_html; ?>
                                </a>
                            </div>
                            <?php
                        }
                    }
                    wp_reset_postdata();
                }

                ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.media-filter-button');
            const items = document.querySelectorAll('.media-item-filter');

            function filterItems(category) {
                items.forEach(item => {
                    if (category === 'all' || item.getAttribute('data-cats') === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            filterItems('all'); // tampilkan semua awalnya

            buttons.forEach(btn => {
                btn.addEventListener('click', function () {
                    buttons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const cat = this.getAttribute('data-cat');
                    filterItems(cat);
                });
            });
        });
        </script>

        <?php

        return ob_get_clean();
    }


    // Jika bukan 'all', ambil semua post sesuai kategori dengan max_post
    ob_start();
    ?>

    <style>
    .media-filter-buttons {
        margin-bottom: 20px;
        text-align: center;
    }
    .media-filter-button {
        display: inline-block;
        margin: 0 8px 12px;
        padding: 8px 18px;
        background: #0073aa;
        color: #fff;
        border-radius: 18px;
        cursor: pointer;
        user-select: none;
        transition: background-color 0.3s ease;
        font-weight: 600;
    }
    .media-filter-button.active,
    .media-filter-button:hover {
        background: #005177;
    }
    .media-kategori-filter-gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        justify-content: center;
    }
    .media-item-filter {
        overflow: hidden;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .media-item-filter:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }
    .media-image {
        display: block;
        width: <?= intval($atts['image_width']); ?>px;
        height: <?= intval($atts['image_height']); ?>px;
        object-fit: cover;
        cursor: pointer;
        border-radius: 8px;
    }
    </style>

    <div class="media-kategori-filter-wrapper">
        <div class="media-filter-buttons">
            <span class="media-filter-button active" data-cat="all">All</span>
            <?php
            $all_categories = get_categories(array('slug' => $category_slugs));
            foreach ($all_categories as $cat): ?>
                <span class="media-filter-button" data-cat="<?= esc_attr($cat->slug); ?>"><?= esc_html($cat->name); ?></span>
            <?php endforeach; ?>
        </div>

        <div class="media-kategori-filter-gallery">
            <?php
            $args = array(
                'post_type' => 'post',
                'posts_per_page' => $max_post,
                'meta_query' => array(
                    array(
                        'key' => '_thumbnail_id',
                        'compare' => 'EXISTS'
                    )
                ),
                'category_name' => implode(',', $category_slugs),
                'orderby' => 'date',
                'order' => 'DESC',
            );

            $query = new WP_Query($args);
            if ($query->have_posts()):
                while ($query->have_posts()):
                    $query->the_post();
                    $post_cats = wp_get_post_categories(get_the_ID());
                    $post_cat_slugs = array();
                    foreach ($post_cats as $pcat_id) {
                        $cat_obj = get_category($pcat_id);
                        if ($cat_obj) {
                            $post_cat_slugs[] = $cat_obj->slug;
                        }
                    }
                    $full_img_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
                    $thumb_html = get_the_post_thumbnail(get_the_ID(), array(intval($atts['image_width']), intval($atts['image_height'])), array('class' => 'media-image'));

                    $data_cats = implode(' ', $post_cat_slugs);
                    ?>
                    <div class="media-item-filter" data-cats="<?= esc_attr($data_cats); ?>">
                        <a href="<?= esc_url($full_img_url); ?>" data-lightbox="media-kategori-filter" data-title="<?= esc_attr(get_the_title()); ?>">
                            <?= $thumb_html; ?>
                        </a>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            else:
                echo '<p>Tidak ada media ditemukan.</p>';
            endif;
            ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('.media-filter-button');
        const items = document.querySelectorAll('.media-item-filter');
        const maxPost = <?= $max_post; ?>;

        function filterItems(category) {
            let shownCount = 0;
            items.forEach(item => {
                const cats = item.getAttribute('data-cats').split(' ');
                if (category === 'all' || cats.includes(category)) {
                    if (shownCount < maxPost) {
                        item.style.display = 'block';
                        shownCount++;
                    } else {
                        item.style.display = 'none';
                    }
                } else {
                    item.style.display = 'none';
                }
            });
        }

        filterItems('all');

        buttons.forEach(btn => {
            btn.addEventListener('click', function () {
                buttons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const cat = this.getAttribute('data-cat');
                filterItems(cat);
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}

add_shortcode('media_kategori_filter', 'media_kategori_filter_shortcode');

// Enqueue lightbox scripts & styles jika shortcode dipanggil
function enqueue_lightbox_for_media_kategori_filter() {
    if (is_singular() || is_page()) {
        global $post;
        if ($post && has_shortcode($post->post_content, 'media_kategori_filter')) {
            wp_enqueue_style('lightbox-css', 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css');
            wp_enqueue_script('lightbox-js', 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js', array('jquery'), '2.11.3', true);
        }
    }
}
add_action('wp_enqueue_scripts', 'enqueue_lightbox_for_media_kategori_filter');
