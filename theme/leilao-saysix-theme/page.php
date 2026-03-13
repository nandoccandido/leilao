<?php get_header(); ?>
<div class="container section">
    <?php
    while (have_posts()):
        the_post();
        the_content();
    endwhile;
    ?>
</div>
<?php get_footer(); ?>
