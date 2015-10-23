<?php
/*
* Plugin Name: Overstock Affiliate Product Embed
* Description: Embed Overstock Products into your Posts 
* Version: 1.0
* Author: Reif Tauati and Travis Hoki
* Author URI: http://www.quora.com/Reif-Tauati
*/

include_once('product_data.php');
include_once('admin_page.php');
include_once('functions.php');

/* Patterns */
include_once('patterns.php');

/* include Pages */
include_once('home_page.php');
include_once('sandbox_page.php');
include_once('doc_page.php');

/* Patterns */
$GLOBALS['developerId'] = get_option('ostk_settings');

add_action( 'wp_enqueue_scripts', 'add_overstock_shortcode_stylesheet' );
add_shortcode('overstock', 'generateShortcodeWidgets');
add_shortcode('sample_widget', 'sampleWidget');
add_action( 'wp_enqueue_scripts', 'load_overstock_js' );

/**
* 1) Load jQuery if it isn't already
* 2) Load custom jQuery
**/
function load_overstock_js() {
  wp_enqueue_script('jquery');
  wp_enqueue_script( 'ostk-custom-jquery', plugin_dir_url( __FILE__ ) . 'flex-slider/jquery.flexslider-min.js', array('jquery'), '1.0', true );
  wp_enqueue_script( 'flex-slider', plugin_dir_url( __FILE__ ) . 'js/dest/overstock-shortcodes.min.js', array('jquery'), '1.0', true );
}//load_overstock_js

/**
* Bring in the stylesheets for the shortcode outputs
**/
function add_overstock_shortcode_stylesheet() {
    wp_register_style( 'flex-slider', plugins_url('flex-slider/flexslider.css', __FILE__) );
    wp_enqueue_style( 'flex-slider' );

    wp_register_style( 'ostk-custom-style', plugins_url('css/dest/overstock-shortcodes.css', __FILE__) );
    wp_enqueue_style( 'ostk-custom-style' );
}//add_overstock_shortcode_stylesheet

/**
 * Overstock Widget Generator
 * consumes a single param -> 'type'
 * then passes the rest of $atts to other functions.
 * Example usage:
 * 1) [overstock type="search" query="book of Mormon"]
 * 2) [overstock type="carousel" category="Pets" number_of_items="5"]
**/
function generateShortcodeWidgets($atts){
	if(is_array($atts) && !in_array($atts['type'], $atts)){ 
    return formatError("Type parameter cannot be empty."); 
  } else if($GLOBALS['developerId'] == '' || is_null($GLOBALS['developerId'])){
    return formatError("Type parameter cannot be empty."); 
  } else {
		$type = (is_array($atts) ? $atts['type'] : null);
    if(areAttributesValid($atts)){
  		switch ($type) {
  			case 'search':
  				return generateLinktoSearchPage($atts);
  				break;
  			case 'link':
  				return generateLinktoAnyPage($atts);
  				break;
  			case 'rectangle':
  				return generateRectangleWidget($atts);
  				break;
  			case 'leaderboard':
  				return generateLeaderboardWidget($atts);
  				break;
  			case 'skyscraper':
  				return generateSkyscraperWidget($atts);
  				break;
  			case 'carousel':
  				return generateCarouselWidget($atts);
  				break;
  			case 'stock_photo':
  				return generateStockPhoto($atts);
  			case 'product_link':
  				return generateProductLinks($atts);
  			case 'product_carousel':
  				return generateProductCarouselWidget($atts);
  				break;
  			default:
          return formatError('Shortcode may have been malformed, check the syntax and try again. Refer to our cheat sheet if you have questions.');
  		}//switch
    }
	}
}//generateShortcodeWidgets

/**
 * Pattern 1 - Search query: takes you to search results page
 * Generate a link to a search results page
 * Query is link text if link_text parameter is empty
 * Usage example 
 * 1) [overstock type="search" query="soccer shoes"]
 * 2) [overstock type="search" query="soccer shoes" link_text="Overstock has great soccer shoes"]
**/
function generateLinktoSearchPage($atts){
	$developerId = $GLOBALS['developerId'];  
	$keywords = (isset($atts['query']) ? "keywords=" . str_replace(" ", "%20", $atts['query']) : null);
  if(empty($keywords)) {
    return formatError('"query" parameter cannot be empty.');
  }

  if(isset($atts['category'])){
    $taxonomyParam = getTaxonomy(htmlspecialchars_decode($atts['category']));
    if(empty($taxonomyParam)) {
      return formatError('"category" not found. Please check spelling and try again.');
    } else {
      $taxonomy = "&taxonomy=" . $taxonomyParam; 
    }
  }

  if(isset($atts['sort_by'])){
    $sortOptionParam = getSortOption($atts['sort_by']);
    if(empty($sortOptionParam)) {
      return formatError('"sort_by" not found. Please check spelling and try again.');
    } else {
      $sortOption = "&taxonomy=" . $sortOptionParam; 
    }
  }

	$murl = "http://www.overstock.com/search?{$keywords}{$taxonomy}{$sortOption}";
  $affiliateLink = generateAffiliateLink($murl);
  $link_text = ($atts['link_text'] != null ? $atts['link_text'] : $atts['query']);
  return '<a href="'.$affiliateLink.'" class="ostk-element ostk-search" '.getLinkTarget($atts).'>'.$link_text.'</a>';
}//generateLinktoSearchPage

/**
 * Pattern 2 - URL: lets you create links to any overstock page
 * Generate a link to a predefined page on Overstock.com
 * Specify the link_text with the link_text attribute
 * Usage example 
 * 1) [overstock type="link" url="http://www.overstock.com/Worldstock-Fair-Trade/Natural-Thailand/9179503/product.html"]
 * 2) [overstock type="link" url="http://www.overstock.com/Worldstock-Fair-Trade/Natural-Thailand/9179503/product.html" link_text="I want to buy this for my wife"]
**/
function generateLinktoAnyPage($atts){
  $atts = shortcode_atts(
      array(
        'url' => 'http://www.overstock.com/', 
        'link_text' => 'A link to Overstock.com',
        'link_target' => 'new_tab'
      ), $atts);
    $affiliateLink = generateAffiliateLink($atts['url']);
    $link_text = $atts['link_text'];
    return '<a href="'.$affiliateLink.'" class="ostk-element ostk-link" '.getLinkTarget($atts).'>'.$link_text.'</a>';
}//generateLinktoAnyPage

/**
 * Pattern 3 - Rectangle: Lets you create a rectangular banner for a SINGLE product
 * Usage example 
 * 1) [overstock type="rectangle" id="10234427"]
 * 
**/
function generateRectangleWidget($atts){
  $atts = shortcode_atts(
    array(
      'type' => null,
      'id' => null,
      'width' => null,
      'link_target' => 'new_tab'
    ), $atts);
     
  $productId = (isset($atts['id']) ? $atts['id'] : null);

  if (is_null($productId)) {
    return formatError("id parameter cannot be empty."); 
  } else {
  	$item = new MultiProductDataFromArray(array($productId), 1);
    if($item->isAllValidProductIDs()){
      $output = '<div class="ostk-element ostk-'.$atts['type'].'" '.getStyles($atts).'>';
        $output .= getBranding();
        $output .= generateRectangleHtmlOutput($item, $atts);
      $output .= '</div>';
      return $output;
    }else{
      return formatError('Invalid product id');
    }
  }
}//generateRectangleWidget


/**
 * Pattern 4 - Leaderboard: Lets you create a leaderboard banner for up to two products
 * Usage Example
 * 1) [overstock type="leaderboard" product_ids="8641092"]
 * 2) [overstock type="leaderboard" product_ids="8641092, 9547029"]
 * 
**/
function generateLeaderboardWidget($atts){
  $atts = shortcode_atts(
    array(
      'product_ids' => null,
      'link_target' => 'new_tab'
    ), $atts);
     
  $product_ids = (isset($atts['product_ids']) ? array_map('trim', explode(',', $atts['product_ids'])) : null);
	foreach($product_ids as $ids) {
  	if(checkForMissingCommas($id) == true) {
  		return formatError("Commas missing between ids, returning...");
  	}
  }//foreach
  $products = new MultiProductDataFromArray($product_ids, count($product_ids));

  $output = '<div class="ostk-element ostk-leaderboard">';
    $output .= getBranding();
    $output .= '<div class="item-holder item-count-'.count($product_ids).'">';
      $output .= generateLeaderboardHtmlOutput($products, $atts);
    $output .= '</div>';
  $output .= '</div>';
  return $output;
}//generateLeaderboardWidget

/**
 * Pattern 5 - Skyscraper: Lets you create a skyscraper banner for up to three products
 * Usage Example
 * 1) [overstock type="skyscraper" product_ids="8641092"]
 * 2) [overstock type="skyscraper" product_ids="8641092, 9547029"]
 * 3) [overstock type="skyscraper" product_ids="8641092, 9547029, 9547023"]
**/
function generateSkyscraperWidget($atts){
  $atts = shortcode_atts(
    array(
      'product_ids' => null,
      'width' => null,
      'link_target' => 'new_tab'
    ), $atts);
  $product_ids = (isset($atts['product_ids']) ? array_map('trim', explode(',', $atts['product_ids'])) : null);
  if(is_null($product_ids)) { 
    return formatError("The product_ids parameter cannot be empty.");
  //code is repeated here, but there will be different html output for different numbers of products
  } else if (count($product_ids) == 1) {
  	//echo "\$product_ids count is 1 (skyscraper)";
  	$products = new MultiProductDataFromArray($product_ids, 1);
    $output = generateSkyscraperHtmlOutput($products, $atts);
  } else if (count($product_ids) == 2) {
  	//echo "\$product_ids count is 2 (skyscraper)";
  	$products = new MultiProductDataFromArray($product_ids, 2);
    $output = generateSkyscraperHtmlOutput($products, $atts);
  } else {
  	foreach($product_ids as $ids) {
    	if(checkForMissingCommas($id) == true) {
    		return formatError("Commas missing between ids, return ing...");
    	}
    }//foreach
  	$products = new MultiProductDataFromArray($product_ids, 3);
  	$output = generateSkyscraperHtmlOutput($products, $atts);
  }
  $output2 = '<div class="ostk-element ostk-skyscraper" '.getStyles($atts).'>';
    $output2 .= getBranding();
    $output2 .= $output;
  $output2 .= '</div>';
  return $output2;
}//generateSkyscraperWidget

/**
 * Pattern 6 - carousel: Lets you create a carousel banner for up to five products
 * Generate a carousel viewer for a $number_of_products
 * Usage Example
 * 1) [overstock type="carousel" product_ids="8641092, 9547029, 9547023"]
 * 2) [overstock type="carousel" category="Pets" sort_by="Top Sellers"]
 * 3) [overstock type="carousel" keywords="soccer shoes" number_of_items="6"]
**/
function generateCarouselWidget($atts){
  $atts = shortcode_atts(
    array(
    'category' => null, 
    'number_of_items' => '5',
    'sort_by' => null, 
    'keywords' => null,
    'product_ids' => null,
    'width' => null,
    'link_target' => 'new_tab'
  ), $atts);
  
  $taxonomy = (isset($atts['category'], $atts) ? "&taxonomy=" . getTaxonomy(htmlspecialchars_decode($atts['category'])) : null);
  $sortOption = (isset($atts['sort_by'], $atts) ? "&sortOption=" . getSortOption($atts['sort_by']) : '');
  $keywords = (isset($atts['keywords'], $atts) ? "keywords=" . str_replace(' ', '%20', $atts['keywords']) : null);
  $product_ids = (isset($atts['product_ids']) ? array_map('trim', explode(',', $atts['product_ids'])) : null);
  $limit = ($atts['number_of_items'] <= 10 ? $atts['number_of_items'] : 5);
  
  if (isset($taxonomy) && getTaxonomy(htmlspecialchars_decode($atts['category'])) == false) {
  	return formatError("category=\"{$atts['category']}\" does not match our given categories, please check it.");
  } else if ($taxonomy == null && $keywords == null && $product_ids == null) {
  	return formatError("Some required fields are missing, (category or keywords) or (a list of product_ids)");
  } else if (isset($product_ids)) {
  	  foreach($product_ids as $ids) {
      	if(checkForMissingCommas($id) == true) {
      		return formatError("Commas missing between ids, return ing...");
      	}
      }//foreach
	  $products = new MultiProductDataFromArray($product_ids, count($product_ids));
  } else {
	  $query = "http://www.overstock.com/api/search.json?{$keywords}{$taxonomy}{$sortOption}";
	  $products = new MultiProductDataFromQuery($query, $limit);
  }
	$output = generateCarouselHTML('carousel', $products->getProductList(), $atts);
  $styles = getStyles($atts);
	return '<div class="ostk-element ostk-carousel" '.$styles.'>'.$output.'</div>';
}//generateCarouselWidget

/**
 * Pattern 7 - Image Link: lets you create an image link to a product page (stock photo)
 * Allow users to add stock photos to their posts (and get paid for it).
 * Example Usage:
 * 1) [overstock type="stock_photo" id="8859234"]
**/
function generateStockPhoto($atts){
	$atts = shortcode_atts(
  	  array(
        'id' => null, 
        'height'=> null, 
        'width' => null, 
        'image_number' => '1', 
        'custom_css' => null,
        'link_target' => 'new_tab'
      ), $atts);
    $id = (isset($atts['id']) ? $atts['id'] : null);
    $item = new SingleProductData($id);
    $output = generateStockPhotoHtmlOutput($item, $atts);

    $output2 = '<div class="ostk-element ostk-stock-photo" '.getStyles($atts).'>';
      $output2 .= $output;
    $output2 .= '</div>';
    return $output2;
}//generateStockPhoto

/**
 * Pattern 8 - Product Details Link
 * Allow users to create easy links to products they are showcasing.
 * Example Usage:
 * 1) [overstock type="product_link" id="8859234"]
**/
function generateProductLinks($atts){
	$atts = shortcode_atts(
  	  array(
        'id' => null,
        'display' => null,
        'link_target' => 'new_tab'
      ), $atts);
    $item = new SingleProductData($atts['id']);
    $display = trim($atts['display']);
    $output;
    switch ($display) {
      case 'name':
      $output = $item->getName();
	    break;
	  case "price":
	  	$output = $item->getPrice();
  	  break;
	  case 'description':
      $output = $item->getDescription();
	    break;
	  default:
	  	return formatError('We do not recognize your display input, please check it.');
    }//switch
    return '<a href="'.$item->getAffiliateUrl().'" class="ostk-element ostk-product-link" '.getLinkTarget($atts).'>'.$output.'</a>';
}//generateProductLinks

/**
 * Pattern 9 : product_carousel 
 * Lets you create a carousel for a single product, it shows all product photos
 * Usage Example
 * 1) [overstock type="product_carousel" id="6143359"]
**/
function generateProductCarouselWidget($atts){
  $atts = shortcode_atts(
    array(
    'id' => null,
    'width' => null,
    'link_target' => 'new_tab'
  ), $atts);
  $item = new SingleProductData($atts['id']);
  $output = generateCarouselHTML('product_carousel', $item, $atts);
  return '<div class="ostk-element ostk-carousel" '.getStyles($atts).'>'.$output.'</div>';
}//generateProductCarouselWidget

/**
 * Sample Widget takes $productId returns ProductData object
**/
function sampleWidget($atts) {
  $atts = shortcode_atts(
  	  array(
        'id' => ''
      ), $atts);
      
  $productId = $atts['id'];
  $item = new SingleProductData($productId);
  $output =
<<<HTML
  <p> The name is <b>{$item->getName()}</b></p>
  <p> The price is <b>{$item->getPrice()}</b></p>
  <p> The rating (as decimal is) <b>{$item->getAverageReviewAsDecimal()}</b></p>
  <p> The rating (as gif is)... see below</p>
  <img src= "{$item->getAverageReviewAsGif()}"/>
  <p> <b>Large image:</b></p>
  <a href="{$item->getAffiliateUrl()}"><img src= {$item->getImage_Large()} /></a>
  <p> <b>Medium image:</b></p>
  <a href="{$item->getAffiliateUrl()}"><img src= {$item->getImage_Medium()} /></a>
  <p> <b>Small image:</b></p>
  <a href="{$item->getAffiliateUrl()}"><img src= {$item->getImage_Thumbnail()} /></a>
  <p>The url link is <a href="{$item->getAffiliateUrl()}"><b>here, click me!</b></a></p>
  <p>Also, all photos are clickable.<p>
HTML;
  return $output;
}//sampleWidget

function getBranding(){
  $output = '<div class="branding">';
    $output .= '<img src="'.plugin_dir_url( __FILE__ ).'images/overstock-logo-white.png" width="110" height="30"/>';
  $output .= '</div>';
  return $output;
}//getBranding

function getStyles($atts){
  $output;
  if(isset($atts['width'])){
    $output .= 'width:'.$atts['width'].';';
  }
  return 'style="'.$output.'"';
}//getStyles

function getLinkTarget($atts){
  $output;
  if(isset($atts['link_target'])){
    $link_target = $atts['link_target'];
    switch($link_target){
      case 'current_tab':
        $output = '_self';
        break;
      case 'new_window':
        $output = '_parent';
        break;
      default:
        $output = '_blank';
    }//switch
    return "target='".$output."'";
  }else{
    return;
  }
}//getLinkTarget
?>