<?php

/**
 * Plugin Name:       Manage RentCafé Floorplans
 * Description:       A simple way to manage floor plans through the RentCafé Floorplans API.
 * Version:           0.1.0
 * Author:            Rob Myrick
 * Author URI:        https://fervorcreative.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rentcafe
 */

class ManageRentcafe {

    private $version;

    function __construct() {

      $theme = wp_get_theme();
      $this->version = $theme->Version;

      //Register the Floorplans Custom Post Type
      add_action('init', [ $this, 'register_post_type_floorplans']);

      //Connect to the Rentcafe API
      add_action('init', [ $this, 'rentcafe_connect']);

      //Populate the floorplans data each time Floorplans CPT is visted
      add_action('init', [ $this, 'rentcafe_populate_floorplans']);

      //Add query args for floorplan layouts
      add_action('init', [ $this, 'action_add_query_vars']);

      //Enqueue frontend scripts
      add_action('wp_enqueue_scripts', [ $this, 'action_enqueue_scripts']);

      //Enqueue frontend styles
      add_action('wp_enqueue_scripts', [ $this, 'action_enqueue_styles']);

      //Enqueue Admin scripts
      add_action('admin_enqueue_scripts', [ $this, 'action_admin_enqueue_scripts']);

      //Enqueue Admin styles
      add_action('admin_enqueue_scripts', [ $this, 'action_admin_enqueue_styles']);

      //Hide the Gutenberg content editor in the floorplans Custom Post Type
      add_filter('use_block_editor_for_post_type', [ $this, 'remove_guttenberg_from_pages'], 10, 2);

      //Display the floorplan data with [floorplans] shortcode
      add_shortcode('floorplans', [ $this, 'rentcafe_display_floorplans']);

    }

    public function action_add_query_vars() {
      global $wp;
      $wp->add_query_var('layout');
    }

    public function action_enqueue_scripts() {

    }

    public function action_enqueue_styles() {
      wp_enqueue_style('styles', plugins_url( 'css/styles.css', __FILE__ ), array(), null);
    }

    public function action_admin_enqueue_scripts($hook_suffix) {
      $cpt = 'floorplans';

      if(in_array($hook_suffix, array('post.php', 'post-new.php')))
        $screen = get_current_screen();

      if(is_object($screen) && $cpt == $screen->post_type):
        wp_enqueue_script('scripts-admin', plugins_url( 'js/scripts-admin.js', __FILE__ ), array('jquery'), '1.0.0', false);
      endif;
    }

    public function action_admin_enqueue_styles($hook_suffix) {
      $cpt = 'floorplans';

      if(in_array($hook_suffix, array('post.php', 'post-new.php')))
        $screen = get_current_screen();

      if(is_object($screen) && $cpt == $screen->post_type):
        wp_enqueue_style( 'styles-admin', plugins_url( 'css/styles-admin.css', __FILE__ ), array(), null);
      endif;
    }

    public function action_login_enqueue_styles() {

    }

    public function register_post_type_floorplans() {

      register_post_type('floorplans', [
        'labels' => [
          'name' => 'Floorplans',
          'singular_name' => 'Floorplan',
          'add_new_item' => 'Add New Floorplan',
          'edit_item' => 'Edit Floorplan',
        ],
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'hierarchical' => false,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 99,
        'menu_icon' => 'dashicons-admin-home',
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'can_export' => true,
        'has_archive' => false,
        'exclude_from_search' => false,
        'publicly_queryable' => false,
        'capability_type' => 'page',
        'show_in_rest' => true,
      ]);
    }

    public function wp_insert_attachment_from_url( $url, $parent_post_id = null ) {

      if ( ! class_exists( 'WP_Http' ) ) {
        require_once ABSPATH . WPINC . '/class-http.php';
      }

      $http     = new WP_Http();
      $response = $http->request( $url );
      if ( 200 !== $response['response']['code'] ) {
        return false;
      }

      $upload = wp_upload_bits( basename( $url ), null, $response['body'] );
      if ( ! empty( $upload['error'] ) ) {
        return false;
      }

      $file_path        = $upload['file'];
      $file_name        = basename( $file_path );
      $file_type        = wp_check_filetype( $file_name, null );
      $attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
      $wp_upload_dir    = wp_upload_dir();

      $post_info = array(
        'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
        'post_mime_type' => $file_type['type'],
        'post_title'     => $attachment_title,
        'post_content'   => '',
        'post_status'    => 'inherit',
      );

      // Create the attachment.
      $attach_id = wp_insert_attachment( $post_info, $file_path, $parent_post_id );

      // Include image.php.
      require_once ABSPATH . 'wp-admin/includes/image.php';

      // Generate the attachment metadata.
      $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );

      // Assign metadata to attachment.
      wp_update_attachment_metadata( $attach_id, $attach_data );

      return $attach_id;

    }

    public function rentcafe_populate_floorplans() {

      if ($this->$formatted_data): foreach ($this->$formatted_data as $data):

        foreach ($data->floorplans as $floorplan):
          $property_id = get_field('property_id', 'options'); 
          $onsite_property_id = get_field('onsite_property_id', 'options');
          $name = $floorplan->FloorplanName;
          $fp_id = $floorplan->FloorplanId;
          $fp_name_slug = strtolower( str_replace(array('.', '_', ' '), '', $name ) );
          $bathrooms = round( $floorplan->Baths );
          $minimum_rent = number_format($floorplan->MinimumRent);
          $maxRent = number_format($floorplan->MaximumRent);
          $square_feet = number_format($floorplan->MinimumSQFT);
          $bedrooms = $floorplan->Beds;
          $floorplan_url = $floorplan->AvailabilityURL;
          $floorplan_img_url = $floorplan->FloorplanImageURL;
          $floorplan_img_alt = $floorplan->FloorplanImageAltText;
          $unitsAvailable = $floorplan->AvailableUnitsCount;

          // We cannot rely on the client to activate specials on their own, so we don't use this variable
          //$has_special = $floorplan->FloorplanHasSpecials;

          // Instead we setup our own fiels for Floorplan Specials
          $has_special = false;
          $the_special = get_field('the_special');

          // Fallback if no floorplan URL
          if ($floorplan_url == ''):
            $floorplan_url = 'https://www.on-site.com/apply/property/'.$onsite_property_id.'?floorplan='.$name;
          endif;

          if ($property_id!==''): 

            $post_info = array(
              'post_type'       => "floorplans",
              'post_title'      => $name, // i.e. 'Georgia'
              'post_name'       => $name, // i.e. 'GA'; this is for the URL
              'post_status'     => "publish",
              'comment_status'  => "closed",
              'ping_status'     => "closed",
              'post_parent'     => "0",
            );
            
            if (!get_page_by_path($name,OBJECT,'floorplans')):

              //Create the floorplan post
              $post_id = wp_insert_post($post_info);

            else: 

              //Update the floorplan post
              $post_id = wp_update_post($post_info);

            endif; 

            //Grab the floorplan image url and upload it to the media folder

            // $attach_id = self::wp_insert_attachment_from_url($floorplan_img_url, $post_id);

            // Set a post thumbnail. So it links the attachment id to the corresponding post id
            // set_post_thumbnail($post_id, $attach_id );

            update_field('name', $name, $post_id);

            if ($bedrooms==0):
              update_field('style', 'studio', $post_id);
            elseif ($bedrooms==1):
              update_field('style', 'one-bedroom', $post_id);
            elseif ($bedrooms==2):
              update_field('style', 'two-bedrooms', $post_id);
            elseif ($bedrooms==3):
              update_field('style', 'three-bedrooms', $post_id);
            endif;

            update_field('bedrooms', $bedrooms, $post_id);
            update_field('bathrooms', $bathrooms, $post_id);
            update_field('square_feet', $square_feet, $post_id);
            update_field('bedrooms', $bedrooms, $post_id);
            update_field('minimum_rent', $minimum_rent, $post_id);
            update_field('apply_now_link', $floorplan_url, $post_id);
            update_field('image_url', $floorplan_img_url, $post_id);
            update_field('units_available', $unitsAvailable, $post_id);
            update_field('has_special', $has_special, $post_id);
            update_field('the_special', 'Add a special to this floorplan by typing here.', $post_id);

          endif;
        endforeach;


      endforeach; endif;
    }

    public function remove_guttenberg_from_pages() {
      if ($post_type === 'floorplans') return false;
        return $current_status;
    }

    public function rentcafe_connect() {

      $requestType = 'floorplan';
      $apiToken = get_field('rentcafe_floorplans_api_token', 'options');
      $propertyCode = get_field('property_id', 'options'); //Data being pulled from Alta Raintree for testing
      $apiURL = 'https://api.rentcafe.com/rentcafeapi.aspx?requestType='.$requestType.'&apiToken='.$apiToken.'&propertyCode='.$propertyCode;
      $token          = $apiToken;
      $property_code  = $propertyCode;
      $FLOORPLANS   = self::rentcafe_get_data('floorplan', $token, $property_code);
      $UNITS 			  = self::rentcafe_get_data('apartmentavailability', $token, $property_code);

      $this->$formatted_data = self::rentcafe_format_data($FLOORPLANS, $UNITS);

      return $this->$formatted_data;
    }

    public function rentcafe_get_data($service, $token, $property_code) {

      $data = wp_remote_get('https://domain.securecafe.com/rentcafeapi.aspx?requestType=' . $service . '&apiToken=' . $token . '&propertycode=' . $property_code);

      return json_decode($data['body']);
    }

    public function rentcafe_format_data($FLOORPLANS, $UNITS) {

      $data = (object)[];

      foreach ($FLOORPLANS as $f => $floorplan) {
        if(isset($floorplan->Error)) break;
        $Units = [];
        foreach ($UNITS as $u => $unit) {
          if(isset($unit->Error)) break;
          if($unit->FloorplanId == $floorplan->FloorplanId) {
            array_push($Units, $unit);
          }
        }

        $Units = (object)$Units;

        $floorplan->Units = (object)[];
        $floorplan->Units = $Units;

        if(count(array($Units))) {
          if($floorplan->MinimumRent >= -1) {
            $FloorplanName = self::rentcafe_get_floorplan_name($floorplan->Beds);
            $floorCat = $FloorplanName;
            if (!isset($data->$floorCat)) {
                $data->$floorCat = (object)[];
                $data->$floorCat->Name = $FloorplanName;
                $data->$floorCat->Slug = self::rentcafe_get_floorplan_slug($FloorplanName);
                $data->$floorCat->Short = self::rentcafe_get_floorplan_short($FloorplanName);
                $data->$floorCat->Order = self::rentcafe_get_floorplan_order($FloorplanName);
                $data->$floorCat->floorplans = [];
            }
            array_push($data->$floorCat->floorplans, $floorplan);
          }
        }
      }

      $data = (array)$data;

      usort($data, "self::rentcafe_cmp");

      $data = (object)$data;

      return $data;
    }

    public function rentcafe_get_floorplan_name($id) {
      switch($id){
        case 0:
          return 'Studio';
          break;
        case 1:
          return '1 Bedroom';
          break;
        case 2:
          return '2 Bedrooms';
          break;
        default:
          return $id;
          break;
      }
    }

    public function rentcafe_get_floorplan_slug($id) {
      switch($id){
        case 'Studio':
          return 'studio';
          break;
        case '1 Bedroom':
          return '1-bedroom';
          break;
        case '2 Bedrooms':
          return '2-bedroom';
          break;
        default:
          return $id;
          break;
      }
    }

    public function rentcafe_get_floorplan_short($id) {
      switch($id){
        case 'Studio':
          return '0';
          break;
        case '1 Bedroom':
          return '1';
          break;
        case '2 Bedrooms':
          return '2';
          break;
        default:
          return $id;
          break;
      }
    }

    public function rentcafe_get_floorplan_order($id) {
      switch($id){
        case 'Studio':
          return 0;
          break;
        case '1 Bedroom':
          return 1;
          break;
        case '2 Bedrooms':
          return 2;
          break;
        default:
          return $id;
          break;
      }
    }

    public function rentcafe_cmp($a, $b) {
      if ($a->Order == $b->Order) {
        return 0;
      }
      return ($a->Order < $b->Order) ? -1 : 1;
    }

    public function rentcafe_display_floorplans() {

      $property_id = get_field('property_id', 'options');

      if ($property_id!==''): 

        //Ensure the $post global is available

        global $post;
        global $wp_query;

        //Query the "Floorplans" Custom Post Type

        $args = array(
          'post_type' => 'floorplans',
          'post_status' => 'publish',
          'posts_per_page' => -1,
          'orderby' => 'title',
          'order' => 'ASC',
        );

        $floorplans_data = new WP_Query($args);

        if ($floorplans_data->have_posts()):
          while($floorplans_data->have_posts()): $floorplans_data->the_post();
            $layout[] = get_field('style')['value'];
          endwhile;
        endif; wp_reset_postdata();

        $layouts_array = array_unique($layout);

        $layout = '';

        if (in_array(get_query_var('layout'), $layouts_array)):
          $layout = get_query_var('layout');
          echo '<div id="'.esc_attr($layout).'"></div>';
        endif; ?>

        <div id="view-property-map"></div>

        <section id="floorplans-block" class="floorplans">

          <div class="grid-container floorplans__tabs full bg-green">
            <div class="grid-x align-middle">
              <div class="cell">
                <div class="grid-container">
                  <div class="grid-x grid-padding-x grid-margin-x">
                    <div class="cell">
                      <ul data-tabs>
                        <li class=""><a href="#property-map" <?php echo esc_attr($layout=='' ? 'data-tabby-default' : ''); ?>>Building</a></li>
                        <li class=""><a href="#floorplans" <?php echo esc_attr($layout!=='' ? 'data-tabby-default' : ''); ?>>Floor Plans</a></li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="grid-container full floorplans__content">

            <?php if (!$floorplans_data->have_posts()): ?>

              <div class="grid-x">
                <div class="cell text-center">
                  <h2>Floor Plans Coming Soon</h2>
                </div>
              </div>

            <?php else: ?>

              <div class="grid-x">

                <div class="cell">
                  <div id="property-map">
                    <section class="property-map">
                      <div class="grid-container">
                        <div class="grid-x align-center">
                          <div class="page-content-col cell large-10">
                            <img src="<?php echo get_template_directory_uri() .'/assets/images/property-map-placeholder.jpg'; ?>" alt="Floor Plan Image" />
                          </div>
                        </div>
                      </div>
                    </section>
                  </div>
                </div>

                <div class="cell">
                  <div id="floorplans">
                    <section class="floorplans__block">
                      <div class="grid-container">
                        <div class="grid-x grid-padding-x grid-padding-y grid-margin-x grid-margin-y align-center">
                          <div class="cell">
                            <div class="grid-container">
                              <div class="grid-x align-center">

                                <div class="floorplans__filters-wrapper cell medium-12 large-8 flex-container align-center">

                                  <div class="floorplans__filters floorplans__filters-mobile flex-container align-middle show-for-small hide-for-medium">

                                    <!-- Begin Mobile Filter -->

                                    <div class="filters mobile">
                                      <div class="filters-mobile__switcher">

                                        <button class="filters-mobile__selected">
                                          <a class="" title="" href="#">
                                            FILTERS
                                            <svg width="11.414" height="7.121" viewBox="0 0 11.414 7.121"><path d="M6618.716,1005l5,5-5,5" transform="translate(1015.707 -6618.009) rotate(90)"/>
                                            </svg>
                                          </a>
                                        </button>

                                      </div>
                                    </div>

                                  </div>

                                  <div class="floorplans__filters floorplans__filters-desktop flex-container align-spaced hidden">

                                    <!-- Begin Sizes Filter -->

                                    <div class="filters sizes">
                                      <div class="filters-sizes__switcher">

                                        <button class="filters-sizes__selected">
                                          <a class="" title="" href="#">
                                            ALL SIZES
                                            <svg width="11.414" height="7.121" viewBox="0 0 11.414 7.121"><path d="M6618.716,1005l5,5-5,5" transform="translate(1015.707 -6618.009) rotate(90)"/>
                                            </svg>
                                          </a>
                                        </button>

                                        <ul class="filters-sizes__options" data-filter-group>
                                          <?php

                                            $sizes = array(
                                              'mix' => 'ALL SIZES',
                                              'one-bedroom' => '1 BEDROOM',
                                              'two-bedrooms' => '2 BEDROOMS',
                                              'three-bedrooms' => '3 BEDROOMS'
                                            );

                                            foreach ($sizes as $key=>$value):
                                              ?>

                                              <li class="filters-sizes__option">
                                                <a href="javascript:;" data-filter="<?php echo '.'.esc_attr($key); ?>"><?php echo esc_html($value); ?></a>
                                              </li>
                                              <?php
                                            endforeach;
                                          ?>
                                        </ul>
                                      </div>
                                    </div>

                                    <!-- Begin Prices Filter -->

                                    <div class="filters prices">
                                      <div class="filters-prices__switcher">

                                        <button class="filters-prices__selected">
                                          <a class="" title="" href="#">
                                            ANY PRICE
                                            <span class="arrow-down">
                                              <svg width="11.414" height="7.121" viewBox="0 0 11.414 7.121"><path id="Path_21162" data-name="Path 21162" d="M6618.716,1005l5,5-5,5" transform="translate(1015.707 -6618.009) rotate(90)"/>
                                              </svg>
                                            </span>
                                          </a>
                                        </button>

                                        <ul class="filters-prices__options" data-filter-group>
                                          <?php

                                            $prices = array(
                                              'mix' => 'ANY PRICE',
                                              'price-range1' => 'UNDER $1,000',
                                              'price-range2' => '$1,000 - $1,500',
                                              'price-range3' => '$1,500 - $2,000',
                                              'price-range4' => 'OVER $2,000'
                                            );

                                            foreach ($prices as $key=>$value):
                                              ?>

                                              <li class="filters-prices__option">
                                                <a href="javascript:;" data-filter="<?php echo '.'.esc_attr($key); ?>"><?php echo esc_html($value); ?></a>
                                              </li>
                                              <?php
                                            endforeach;
                                          ?>
                                        </ul>
                                      </div>
                                    </div>

                                    <!-- Begin Square Footage Filter -->

                                    <div class="filters sqft">
                                      <div class="filters-sqft__switcher">

                                        <button class="filters-sqft__selected">
                                          <a class="" title="" href="#">
                                            ALL SQ FT
                                            <svg width="11.414" height="7.121" viewBox="0 0 11.414 7.121">
                                              <path d="M6618.716,1005l5,5-5,5" transform="translate(1015.707 -6618.009) rotate(90)"/>
                                            </svg>
                                          </a>
                                        </button>

                                        <ul class="filters-sqft__options" data-filter-group>
                                          <?php

                                            $sqfts = array(
                                              'mix' => 'ANY SQFT',
                                              'sqft-range1' => 'UNDER 800 SQFT',
                                              'sqft-range2' => '800 - 1,000 SQFT',
                                              'sqft-range3' => '1,000 - 1,200 SQFT',
                                              'sqft-range4' => 'OVER 1,200 SQFT'
                                            );

                                            foreach ($sqfts as $key=>$value):
                                              ?>

                                              <li class="filters-sqft__option">
                                                <a href="javascript:;" class="sqft-<?php echo esc_attr($key); ?>" data-filter="<?php echo '.'.esc_attr($key); ?>"><?php echo esc_html($value); ?></a>
                                              </li>
                                              <?php
                                            endforeach;
                                          ?>
                                        </ul>
                                      </div>
                                    </div>



                                  </div>
                                </div> <!-- .cell -->

                              </div>
                            </div>

                          </div>

                          <div class="cell floorplans__content-wrapper">
                            <div class="grid-container full">
                              <div class="grid-x grid-padding-y align-center">

                                <div class="cell large-10">

                                  <div class="floorplans__layouts-error hidden">
                                    <p>There are no results that match your selection.</p>
                                  </div>

                                  <div class="floorplans__layouts">
                                    <?php

                                      $i = 0;
                                      while($floorplans_data->have_posts()): $floorplans_data->the_post();

                                        $name = get_field('name');
                                        $style_value = get_field('style')['value'];
                                        $style_label = get_field('style')['label'];
                                        $slug = str_replace('.', '_', get_field('name'));
                                        $sqft_raw = str_replace(',','',get_field('square_feet'));
                                        $sqft = get_field('square_feet');
                                        $beds = get_field('bedrooms');
                                        $baths = get_field('bathrooms');
                                        $minimum_rent_raw = str_replace(',','',get_field('minimum_rent'));
                                        $minimum_rent = get_field('minimum_rent');
                                        $image = get_field('image_url');
                                        $units_available = get_field('units_available');

                                        //The Apply Now Link, whether it be generated by the RentCafe API, or added as a custom link
                                        $apply_now = get_field('apply_now_link');

                                        // OnSite is a service that handles applications for floorplans, separately from RentCafe
                                        // Below is a field that holds the property ID and gets added to the Apply Now link

                                        $onsite_property_id = get_field('onsite_property_id', 'options');

                                        // Floorplan Specials
                                        // Typically, RentCafe returns -1 if the floorplan is allowed to have specials, and returns 0 otherwise
                                        // However, the client typically asks us to add a special even when it's not turned-on in RentCafe
                                        // Therefore, we setup our own ACF Fields to handle this

                                        $has_special = get_field('has_special');
                                        $the_special = get_field('the_special');

                                        //Setup ordering based on the style of floorplan

                                        if ($minimum_rent_raw < 1000):
                                          $price_range = 'price-range1';
                                        elseif ($minimum_rent_raw >= 1000 && $minimum_rent_raw <= 1500):
                                          $price_range = 'price-range2';
                                        elseif ($minimum_rent_raw >= 1500 && $minimum_rent_raw <= 2000):
                                          $price_range = 'price-range3';
                                        elseif ($minimum_rent_raw > 1200):
                                          $price_range = 'price-range4';
                                        endif;

                                        if ($sqft_raw < 800):
                                          $sqft_range = 'sqft-range1';
                                        elseif ($sqft_raw >= 800 && $sqft_raw <= 1000):
                                          $sqft_range = 'sqft-range2';
                                        elseif ($sqft_raw >= 1000 && $sqft_raw <= 1200):
                                          $sqft_range = 'sqft-range3';
                                        elseif ($sqft_raw > 1200):
                                          $sqft_range = 'sqft-range4';
                                        endif; ?>

                                        <div class="floorplans__layouts-row-wrapper mix <?php echo esc_attr($style_value .' '. $price_range .' '.$sqft_range); ?>">

                                          <div class="floorplans__layouts-row-summary">
                                            <div class="name"><span><?php echo esc_html($name); ?></span></div>
                                            <div class="bedrooms"><span><?php echo esc_html($style_label); ?></span></div>
                                            <div class="bathrooms"><span><?php echo esc_html($baths . ' Bath'); ?></span></div>
                                            <div class="square-footage"><span><?php echo esc_html($sqft . ' SF'); ?></span></div>
                                            <div class="price">
                                              <span class="starting">
                                                <?php echo ($minimum_rent>0 ? 'Starting at: $'.esc_html($minimum_rent).($has_special?'<span class="special">*</span>':'') : '<p><a href="/contact-us/">Inquire For Details</a></p>'); ?>
                                              </span>
                                            </div>
                                            <div class="button-wrap">
                                              <a href="#floorplan-<?php echo esc_attr($slug); ?>" class="button green-light" data="view-plan">
                                                <span>View Floorplan</span>
                                              </a>
                                            </div>
                                          </div>

                                          <div class="floorplans__layouts-row-details" id="floorplan-<?php echo esc_attr($slug); ?>">

                                            <div class="left">

                                              <h3 class="name"><?php echo esc_html('Floor Plan '.$name); ?></h3>
                                              <p class="style"><?php echo esc_html($style_label); ?></p>

                                              <?php
                                                if ($has_special):
                                                  $the_special = get_field('the_special', 'options');
                                                endif;
                                              ?>

                                              <?php if ($units_available > 0): ?>

                                                <?php if ($units_available <= 5 || ($has_special && !empty($the_special))): ?>
                                                  <div class="floorplans__layouts-callout">

                                                    <?php if ($units_available <= 5): ?>
                                                      <h5 class="hurry-notifier">Hurry! This floor plan is selling fast, only <?php echo esc_html($units_available); ?> left!</h5>
                                                    <?php endif; ?>

                                                    <?php if ($has_special && !empty($the_special)): ?>
                                                      <p class="current-special">Current Special: <?php echo esc_html($the_special); ?></p>
                                                    <?php endif; ?>

                                                  </div> <!-- .callout -->
                                                <?php endif; ?>

                                                <?php if ($apply_now): ?>
                                                  <p class="apply"><a href="<?php echo esc_url($apply_now); ?>" target="_blank" class="button black">Apply Now</a></p>
                                                <?php endif; ?>

                                              <?php else: ?>
                                                <p><a href="/contact-us/" class="button black"> Inquire For Details</a></p>
                                              <?php endif; ?>

                                            </div> <!-- .left -->

                                            <?php $images = explode(',', $image); ?>

                                            <div class="right">
                                              <div class="alta-slider-floorplans">
                                                <?php foreach ($images as $image): ?>
                                                  <div>
                                                    <a href="<?php echo esc_url($image); ?>" class="mfp-image">
                                                      <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($current_theme); ?>">
                                                    </a>
                                                  </div>
                                                <?php endforeach; ?>
                                              </div>
                                            </div>
                                          </div>

                                        </div>
                                        <?php
                                      $i++; endwhile; wp_reset_postdata();
                                    ?>
                                  </div> <!-- .floorplans__layouts -->

                                </div>
                              </div>

                             
                              <div class="grid-x grid-padding-y grid-margin-y align-center align-middle text-center">
                                <div class="cell floorplan-disclaimer">
                                  <p>*Square footages are approximate and may vary per apartment.</p>
                                </div>
                              </div>
                           
                            </div>
                          </div>
                        </div>
                      </div>
                    </section>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

        </section>

        <?php
      endif;
    }


} //FervorRentcafe

$floorplans = new ManageRentcafe();

?>
