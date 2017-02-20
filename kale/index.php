<?php
/**
* The main template file.
* 
* @package kale
*/
?>
<?php get_header(); ?>

<?php if(is_front_page() && !is_paged() ) { 
    get_template_part('parts/frontpage', 'banner'); 
    get_template_part('parts/frontpage', 'featured'); 
} ?>

<!-- Two Columns -->
<div class="row two-columns">
    <?php get_template_part('parts/feed'); ?>
    <?php get_sidebar(); ?>
</div>
<!-- /Two Columns -->
<hr />

<?php if(is_front_page() && !is_paged() ) { 
    get_template_part('parts/frontpage', 'large'); 
} ?>

<?php get_footer(); ?>