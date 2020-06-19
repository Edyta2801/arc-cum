<?php
/**
 * The Template for displaying all single posts.
 *
 * @package GeneratePress
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

get_header(); ?>

	<div id="primary" <?php generate_do_element_classes('content'); ?>>
		<main id="main" <?php generate_do_element_classes('main'); ?>>
			<?php
            /**
             * generate_before_main_content hook.
             *
             * @since 0.1
             */
            do_action('generate_before_main_content');

            while (have_posts()) : the_post();

                get_template_part('content', 'single');

                // If comments are open or we have at least one comment, load up the comment template.
                if (comments_open() || '0' != get_comments_number()) :
                    /**
                     * generate_before_comments_container hook.
                     *
                     * @since 2.1
                     */
                    do_action('generate_before_comments_container');
                    ?>

					<div class="comments-area">
						<?php comments_template(); ?>
					</div>

					<?php
                endif;

            endwhile;

            /**
             * generate_after_main_content hook.
             *
             * @since 0.1
             */
            do_action('generate_after_main_content');
            ?>
		</main><!-- #main -->
	</div><!-- #primary -->
    <?php




function cptPaginate2()
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
        echo '<div class="pagination-box align-full">';
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

cptPaginate2();

?>

	<?php
    /**
     * generate_after_primary_content_area hook.
     *
     * @since 2.0
     */
    do_action('generate_after_primary_content_area');
    generate_construct_sidebars();

get_footer();
