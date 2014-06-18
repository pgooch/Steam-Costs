<?php
// Before we get started we need to tell PHP what our timezone is, or it'll flip out.
date_default_timezone_set('America/Los_Angeles'); // You can also just use "UTC"

// Before we determine whether or not were going to scrape we should check and see if a json file from a previous scrape was passed. 
// It can be passed as either a CLI arg or a get var.
if(isset($argv[1])){
	list($arg,$val)=explode('=',$argv[1]);
	if($arg=='file'){$file=$val;}
}
if(isset($_GET['file'])==1){
	$file = $_GET['file'];
}

// Do you want to save the data to files?
$save = true;

// Here is a list of the categories you want to scrape, this should contain all items with a cost.
$categories = array(
	998 => 'Games',
	21  => 'Downloadable Content',
	994 => 'Software',
);

// if a file is set were going to load that data as an array, otherwise were going to scrape.
if(isset($file)){
	$scrape_data = json_decode(file_get_contents($file),true);

}else{

	// And just so we can further modify this search in the future, this is the base URL. [category] and [page] will be replaces with what you'd expect.
	$base_url = 'http://store.steampowered.com/search/?category1=[category]&sort_by=Name&sort_order=ASC&page=[page]';

	// To prevent duplicates in the search results form popping up were going to store everything by ID in a single (very large) array, then play with it later.
	$scrape_data = array();

	// Time to start looping, first by category, then by page
	foreach($categories as $category_id => $category_name){

		// Things to keep track of.
		$still_searching = true;
		$page = 1;

		// This is the actual loop thats pulling the page, and processing it as needed.
		$ch = curl_init();
		while($still_searching){
			if(PHP_SAPI=='cli'){
				// I can actually give feedback when run from CLI
				echo ' Processing page '.$page.'...    '."\r";
			}
			curl_setopt($ch,CURLOPT_URL,get_url($base_url,array('category'=>$category_id,'page'=>$page)));
			curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
			// curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout); // Just in case we need it in the future.
			$scrape = curl_exec($ch);
			
			// We only need to concern outselves with the <a> elements containing the "search_result_row" class
			preg_match_all('~<a href="[^"]+" class="search_result_row[^"]+" ?>.+?</a>~s',$scrape,$scrape);

			// If we diden't find any elements then we know were done and can set $still_searching appropriately, otherwise the real processing beings.\
			if(count($scrape[0])==0){
				// Were done here
				$still_searching = false;
			}else{
				// Process Search Results
				foreach($scrape[0] as $garbage => $result){
					// We store everything based on steam_id to prevent duplicates, so lets find that first.
					preg_match_all('~<a href=".+?steampowered.com/.+?/(.+?)/.+?class="search_result_row.+?>~',$result,$steam_id);
					$steam_id=$steam_id[1][0];
					
					// Start find bits of data and adding it to the scrape_data array.
					if(!isset($scrape_data[$steam_id])){
						$scrape_data[$steam_id] = array('times_seen'=>0);
					}
					$scrape_data[$steam_id]['times_seen']++;
					
					// The Title Name, hardly needed for this, but makes hunting down oddities easier.
					preg_match_all('~<h4>(.+?)</h4>~',$result,$name);
					$scrape_data[$steam_id]['name']=mb_convert_encoding($name[1][0],'HTML-ENTITIES','UTF-8');

					// We know this from the categories array way above
					$scrape_data[$steam_id]['category'] = $category_name;

					// The price, if there is a sale price then we know what both are, otherwise the sale price is actually the regular price as well.
					preg_match_all('~<div class="col search_price">(?:<span.+?><strike>(.+?)</strike></span><br/?>)?(.+?)</div>~',$result,$price);
					if(!isset($price[1][0])){
						// These items have no price, they seem to all be pre-release items (or the odd demo).
						$price[1][0]='No Price';
						$price[2][0]='No Price';
					}
					$scrape_data[$steam_id]['price']['current'] = preg_replace('~&#[0-9]+;~','',$price[2][0]);
					if($price[1][0]==''){
						$scrape_data[$steam_id]['price']['regular'] = preg_replace('~&#[0-9]+;~','',$price[2][0]);
					}else{ // It's on sale
						$scrape_data[$steam_id]['price']['regular'] = preg_replace('~&#[0-9]+;~','',$price[1][0]);
					}

					// Discount Amount
					if($scrape_data[$steam_id]['price']['current']!=$scrape_data[$steam_id]['price']['regular']){
						$scrape_data[$steam_id]['discount'] = round(($scrape_data[$steam_id]['price']['current']/$scrape_data[$steam_id]['price']['regular'])*100);
					}

					// Platforms
					preg_match_all('~<span class="platform_img (.+?)">~',$result,$platforms);
					$scrape_data[$steam_id]['platforms'] = $platforms[1];

					// Genres and Release Date (not done with a strict regular express since exploding seems to get more reliable results)
					preg_match_all('~<p>.+?</span>(?!<)(.+?)</p>~s',$result,$genres_and_release);
					$genres_and_release=explode(' - ',$genres_and_release[1][0].' - ');
					if(strpos($genres_and_release[0],'Released:')!==false || strpos($genres_and_release[0],'Available:')!==false){
						// Some games don't have genres, this can make the date show up as a genre, hopefully we can weed them all out
						$genres_and_release[0] = 'No Genre Given';
					}
					$scrape_data[$steam_id]['genres'] = explode(', ',trim($genres_and_release[0]));
					// Release Dates are missing from a few items, so we'll need to be ready for that.
					if(trim($genres_and_release[1])==''){
						$genres_and_release[1] = 'No Release Date Given';
					}
					preg_match_all('~([A-z]{3}) ([0-9]{1,2}), ([0-9]{4})~',$genres_and_release[1],$release_date);
					if(!isset($release_date[0][0])){
						$release_date[0][0] = 'No Release Date Given';
					}
					$scrape_data[$steam_id]['release_date'] = trim($release_date[0][0]);

					// Metascore
					preg_match_all('~search_metascore">([0-9]+)*</div>~',$result,$metascore);
					if(!isset($metascore[1][0])){
						echo $result;
					}
					$scrape_data[$steam_id]['metascore'] = $metascore[1][0];

				}
			}

			$page++;
			// You can set a page here to force an early stop, if your just testing something
			//if($page>2){$still_searching = false;}
		}
		curl_close($ch);
	}
}

// We now have a detailed scrape of the entire store (well, the categories we care about) and we can now do the fun stuff.
$titles_accounted_for = 0;
$current_items_on_sale = 0;
$savings_percent_total = 0;// used to determin avg amount off
$total_price = 0.00;
$current_price = 0.00;
$price_by_category = array();
$price_by_platform = array();
$price_by_genre = array();

// Loop through and do all the additions
foreach($scrape_data as $steam_id => $data){
	$titles_accounted_for += $data['times_seen'];
	$total_price += $data['price']['regular'];
	$current_price += $data['price']['current'];
	if(isset($data['discount'])){
		$current_items_on_sale++;
		$savings_percent_total += $data['discount'];
	}
	if(!isset($price_by_category[$data['category']])){
		$price_by_category[$data['category']] = 0.00;
	}
	$price_by_category[$data['category']] += $data['price']['regular'];

	// By Platform maths
	foreach($data['platforms'] as $g => $platform){
		if(!isset($price_by_platform[$platform])){
			$price_by_platform[trim($platform)] = 0.00;
		}
		$price_by_platform[trim($platform)] += $data['price']['regular'];
	}

	// By Genre maths
	foreach($data['genres'] as $g => $genre){
		if(!isset($price_by_genre[$genre])){$price_by_genre[$genre] = 0.00;}
		$price_by_genre[trim($genre)] += $data['price']['regular'];
	}
}

/* Random Example Entry
    [271670] => Array
        (
            [times_seen] => 1
            [name] => 10 Second Ninja
            [category] => Games
            [price] => Array
                (
                    [current] => 9.99
                    [regular] => 9.99
                )

            [platforms] => Array
                (
                    [0] => steamplay
                    [1] => win
                    [2] => mac
                )

            [release_date] => Mar 5, 2014
            [genres] => Array
                (
                    [0] => Action
                    [1] => Indie
                )

            [metascore] => 72
        )
*/

// If your in a browser we need to make sure you know it's preformatted.
if(PHP_SAPI!='cli'){
	echo '<pre>';
}else{
	echo "                          \n\n\n";// All those spaces cover the "Processing Page" message.
}
// Output the final display, this is super simple since I want this runable via the command line and be readable.
ob_start();
echo 'Steam Pricing Breakdown '.date('F jS, Y g:ia T')."\n";
echo "\n";
echo 'Items Accounted For: '.number_format($titles_accounted_for)."\n";
echo 'Total Price: '.cash_format($total_price)."\n";
echo 'Current Price: '.cash_format($current_price)."\n";
echo 'Current Number of Items on Sale: '.number_format($current_items_on_sale)."\n";
echo 'Current Savings: %'.number_format(($total_price-$current_price)/$total_price,5).' ('.cash_format($total_price-$current_price).')'."\n";
echo "\n";
echo 'Average Price: '.cash_format($total_price/$titles_accounted_for)."\n";
echo 'Average Current Price: '.cash_format($current_price/$titles_accounted_for)."\n";
echo 'Average Sale Discount: %'.round($savings_percent_total/$current_items_on_sale,3).' ('.cash_format(($total_price-$current_price)/$current_items_on_sale).')'."\n";
echo "\n";
echo 'Regular Price Breakdown by Category'."\n";
$price_by_category=array_map('cash_format',$price_by_category);
make_ascii_list($price_by_category);
echo "\n";
echo 'Regular Price Breakdown by Platform'."\n";
$price_by_platform=array_map('cash_format',$price_by_platform);
make_ascii_list($price_by_platform);
echo "\n";
echo 'Regular Price Breakdown by Genre'."\n";
$price_by_genre=array_map('cash_format',$price_by_genre);
make_ascii_list($price_by_genre);
echo "\n";
$stats=ob_get_flush();

// Were going to try and save this, if we have the permissions needed to
if($save){
	if(!isset($file)){
		$date = date('YmdHis');
		$stats_file = @fopen($date.'.stats.txt','w');
		if($stats_file==false){
			echo 'The script was unable to save the stat and scrape files.';
		}else{
			fwrite($stats_file,$stats);
			$scrape_file = fopen($date.'.scrape.json','w');
			fwrite($scrape_file,json_encode($scrape_data,JSON_PRETTY_PRINT));
			echo 'This information has been saved to "'.$date.'.stats.txt'.'" along with the data used to create it to "'.$date.'.scrape.json'.'".'."\n";
		}
		// And a little final cleanup
		if(PHP_SAPI!='cli'){
			echo '</pre>';
		}else{
			echo "\n\n";
		}
	}
}

// This is the entire array created during the scrape, it's pretty big and you probably don't care about those details unless your developing something
// print_r($scrape_data);

// This is just a simple replacer function.
function get_url($base_url,$replacements){
	foreach($replacements as $key => $value){
		$base_url = str_ireplace('['.$key.']',$value,$base_url);
	}
	return $base_url;
}
// A single place to adjust how the money values are displayed
function cash_format($price){
	$price = round($price,2);
	return '$'.number_format($price,2);
}
// Takes an array and turns it into a formatted, monospaced list.
function make_ascii_list($array){
	ksort($array);
	// Get the longest keys 
	$key_len = 0;
	foreach($array as $key => $val){
		if(strlen($key)>$key_len){$key_len=strlen($key);}
	}
	// Output the formatted list.
	foreach($array as $key => $val){
		echo ucwords(sprintf('%-'.$key_len.'s',$key)).': '.$val."\n";
	}
}
// Takes a multidimensional array and creates an ascii table from it (ended up not using it, but may in the future);
function make_ascii_table($array){
	// Determine how long the cells need to be
	foreach($array as $g => $data){
		foreach($data as $col => $cell){
			if(!isset($cols[$col])){
				$cols[$col] = strlen($col);
			}
			if(strlen($cell)>$cols[$col]){
				$cols[$col] = strlen($cell);
			}
		}
	}
	// Output the header row
	$total_cols = count($cols);
	$row = 0;
	foreach($cols as $col => $len){
		$row++;
		echo sprintf('%-'.$len.'s',$col).' ';
		if($total_cols>$row){
			echo ': ';
		}else{
			echo "\n";
		}
	}
	// Output the table data
	foreach($array as $g => $data){
		$row = 0;
		foreach($cols as $col => $len){
			$row++;
			echo sprintf('%-'.$len.'s',$data[$col]).' ';
			if($total_cols>$row){
				echo ': ';
			}else{
				echo "\n";
			}
		}
	}
}
// Returns the length of the longest value, useful for making ascii tables
function get_longest_value($array){
	$longest = 0;
	foreach($array as $n => $value){
		if(strlen($value)>$longest){
			$longest = strlen($value);
		}
	}
	return $longest;
}