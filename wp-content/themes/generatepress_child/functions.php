<?php

/**

 * GeneratePress child theme functions and definitions.

 *

 * Add your custom PHP in this file.

 * Only edit this file if you have direct access to it on your server (to fix errors if they happen).

 */



function generatepress_child_enqueue_scripts()
{
    if (is_rtl()) {
        wp_enqueue_style('generatepress-rtl', trailingslashit(get_template_directory_uri()) . 'rtl.css');
    }
}

add_action('wp_enqueue_scripts', 'generatepress_child_enqueue_scripts', 100);



function cumulus_child_enqueue_scripts()
{
    wp_enqueue_style('uikit', get_stylesheet_directory_uri() . '/uikit/css/uikit.css');

    // wp_enqueue_style( 'uikit-site-css', get_stylesheet_directory_uri() . '/uikit/css/site.css' );

    // wp_enqueue_style( 'uikit-min', get_stylesheet_directory_uri() . 'uikit/css/uikit.min.css' );
}

add_action('wp_enqueue_scripts', 'cumulus_child_enqueue_scripts', 5);



function cumulus_czepeq_style()
{
    wp_enqueue_style('czepeq_style', get_stylesheet_directory_uri() . '/custom_css/style-czepeq.css');

    wp_enqueue_style('pajlo_style', get_stylesheet_directory_uri() . '/custom_css/style-pajlo.css');

    wp_enqueue_style('custom_style', get_stylesheet_directory_uri() . '/style-custom.css');

    wp_enqueue_style('owl-css-theme', get_stylesheet_directory_uri() . '/owlcarousel/owl.theme.default.css');

    wp_enqueue_style('owl-css', get_stylesheet_directory_uri() . '/owlcarousel/owl.carousel.css');
}

add_action('wp_enqueue_scripts', 'cumulus_czepeq_style', 101);



function cumulus_additional_scripts_footer()
{
    wp_enqueue_script('uikit-icons', get_stylesheet_directory_uri() . '/uikit/js/uikit-icons.js', array(), false, true);

    // wp_enqueue_script( 'uikit-icons-min', get_stylesheet_directory_uri() . '/uikit/js/uikit-icons.min.js', array(), false, true );

    wp_enqueue_script('uikit-js', get_stylesheet_directory_uri() . '/uikit/js/uikit.js', array(), false, true);

    // wp_enqueue_script( 'uikit-js', get_stylesheet_directory_uri() . '/uikit/js/uikit.min.js', array(), false, true );

    wp_enqueue_script('owl-js', get_stylesheet_directory_uri() . '/owlcarousel/owl.carousel.js', array(), false, true);

    wp_enqueue_script('owl-ster-js', get_stylesheet_directory_uri() . '/owlcarousel/owl.ster.js', array(), false, true);
    // add core.js
    wp_enqueue_script('core-js', get_stylesheet_directory_uri() . '/assets/js/core.js', array(), false, true);
}


// add line fort test connection


add_action('wp_enqueue_scripts', 'cumulus_additional_scripts_footer');


// kod dla zmiany logo na białe



function make_white_logo()
{
    $logo_color_checked_values = get_field('logo_color');

    //  var_dump($logo_color_checked_values);

    if ($logo_color_checked_values) :



         echo '<style type="text/css">  .header-image {  -webkit-filter: brightness(0) invert(1);  filter: brightness(0) invert(1);     }</style>';



    endif;
}

add_action('wp_body_open', 'make_white_logo');

// add conutUP.js


add_action('wp_body_open', 'make_white_logo');

function getPostsIdsByPostType($postType):array
{
    $postTypeList = query_posts('post_type='.$postType);
    $postsIDS =[];
    foreach ($postTypeList as $post) {
        $postsIDS[]= $post->ID;
    }

    return $postsIDS;
}

function cptPaginate()
{
    $posts_ids = getPostsIdsByPostType(get_post_type());
    $current = array_search(get_the_ID(), $posts_ids);
    if ($current==0) {
        $prevID = [];
    } else {
        $prevID = $posts_ids[$current-1];
    }
    if ($current==count($posts_ids)) {
        $nextID=[];
    } else {
        $nextID = $posts_ids[$current+1];
    }

    if (!empty($prevID) || !empty($nextID)) {
        echo '<div class="pagination-box">';
    }
    if (!empty($prevID)) {
        echo '<div class="alignleft pagination">';
        echo '<a href="';
        echo get_permalink($prevID);
        echo '"';
        echo 'title="';
        echo get_the_title($prevID);
        echo'">';
        echo get_the_title($prevID);
        echo get_the_post_thumbnail($prevID, 'medium');
        echo '</a>';
        echo "</div>";
    }
    if (!empty($nextID)) {
        echo '<div class="alignright pagination">';
        echo '<a href="';
        echo get_permalink($nextID);
        echo '"';
        echo 'title="';
        echo get_the_title($nextID);
        echo'">';
        echo get_the_title($nextID);
        echo get_the_post_thumbnail($nextID, 'medium');
        echo '</a>';
        echo "</div>";
    }
    if (!empty($prevID) || !empty($nextID)) {
        echo '</div>';
    }
}

function be_dps_add_category_classes($classes)
{
    $categories = get_the_terms(get_the_ID(), 'category');
    if (! empty($categories) && ! is_wp_error($categories)) {
        foreach ($categories as $category) {
            $classes[] = 'cat-' . $category->slug;
        }
    }
    return $classes;
}
  add_filter('display_posts_shortcode_post_class', 'be_dps_add_category_classes');


//   function add_category_to_displaypostsplugin() {
//     $terms = get_the_terms(get_the_ID(), 'portfolio_category');
//     $class = [];
//     foreach ($terms as $term) {
//         $class[] = $term->slug;
//     }
//     $class[]='listing-item';
//   }
//   add_action( 'be_display_posts_shortcode', 'add_category_to_displaypostsplugin' )

// rozmiar obrazka do karuzeli aktualnosci
add_image_size( 'karuzela', 333, 222, true );