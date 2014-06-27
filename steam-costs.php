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

// Loop through the scrape data and do the maths needed for the summary stats
$discounted_titles = array(); // used for that list of discounted titles.
foreach($scrape_data as $steam_id => $data){
	// The majority of the data will be done on a category basis, this is the default array so we can blindly add without checking later.
	if(!isset($category_data[$data['category']])){
		$category_data[$data['category']] = array(
			'Category' => $data['category'],
			'Items' => 0,
			'Disc Items' => 0,
			'Free Items' => 0,
			'Reg Total' => 0.00,
			'Cur Total' => 0.00,
			'Avg Disc %' => 0.00,
			'Avg Disc $' => 0.00,
			'Total Disc %' => 0.00, // This adds the discount amount up, used to determine Avg Disc %
			'Total % Disc' => 0,
			'Total Disc $' => 0.00,
		);
	}
	// From here most the additions are simple.
	$category_data[$data['category']]['Items']++;
	if($data['price']['regular']!=$data['price']['current']){
		$category_data[$data['category']]['Disc Items']++;
		$category_data[$data['category']]['Total Disc %']+=($data['price']['current']/$data['price']['regular'])*100;
		$discounted_titles[$data['name']] = '%'.round(($data['price']['current']/$data['price']['regular'])*100).' Off (Was: $'.$data['price']['regular'].' Now: $'.$data['price']['current'].')';
	}
	if(preg_replace('~[0-9\.]+~','',$data['price']['regular'])!=''){
		$category_data[$data['category']]['Free Items']++;
	}
	$category_data[$data['category']]['Reg Total']+=$data['price']['regular'];
	$category_data[$data['category']]['Cur Total']+=$data['price']['current'];
	
	// By Platform Data
	foreach($data['platforms'] as $g => $platform){
		$by_platform[trim($platform)]['Platform'] = ucwords($platform);	
		$by_platform[trim($platform)]['Items']++;
		$by_platform[trim($platform)]['Reg Total'] += $data['price']['regular'];
		$by_platform[trim($platform)]['Avg $'] = ($by_platform[trim($platform)]['Avg $']*($by_platform[trim($platform)]['Items']-1)/$by_platform[trim($platform)]['Items'])+($data['price']['regular']/$by_platform[trim($platform)]['Items']);
	}
	
	// By Genre maths
	foreach($data['genres'] as $g => $genre){
		$by_genre[trim($genre)]['Genre'] = ucwords($genre);	
		$by_genre[trim($genre)]['Items']++;
		$by_genre[trim($genre)]['Reg Total'] += $data['price']['regular'];
		$by_genre[trim($genre)]['Avg $'] = ($by_genre[trim($genre)]['Avg $']*($by_genre[trim($genre)]['Items']-1)/$by_genre[trim($genre)]['Items'])+($data['price']['regular']/$by_genre[trim($genre)]['Items']);
	}
}
// Do some additional post scrape loop processing.
// Calculate additional category stats
$total_stats = array();
foreach($category_data as $category => $data){
	$category_data[$category]['Avg Disc %'] = $data['Total Disc %']/$data['Disc Items'];
	$category_data[$category]['Avg Disc $'] = ($data['Reg Total']-$data['Cur Total'])/$data['Disc Items'];
	$category_data[$category]['Total % Disc'] = ($data['Reg Total']-$data['Cur Total'])/$data['Reg Total'];
	$category_data[$category]['Total Disc $'] = $data['Reg Total']-$data['Cur Total'];
	// Calculate the Total Stats, we can use category as a base since it has all the data
	$total_stats['Items'] += $data['Items'];
	$total_stats['Regular Price'] += $data['Reg Total'];
	$total_stats['Current Price'] += $data['Cur Total'];
	$total_stats['Free Items'] += $data['Free Items'];
	$total_stats['Disc Items'] += $data['Disc Items'];
	$total_stats['Total Disc %'] += $data['Total Disc %'];
	$total_stats['Total Disc $'] += $data['Total Disc $'];
}

// Clean up the table data for display
$by_platform 	= prettify_table($by_platform);
$by_genre 		= prettify_table($by_genre);
				  ksort($by_genre);
$category_data 	= prettify_table($category_data);
ksort($discounted_titles); 

// Were going to break the big $category_data into smaller pieces for display.
$discounts_by_category = array();
$totals_by_category = array();
foreach($category_data as $category => $data){
	foreach($data as $item => $value){
		switch($item){
			// To both
			case 'Category':
			case 'Disc Items':
				$discounts_by_category[$category][$item] = $value;
				$totals_by_category[$category][$item] = $value;
			break;
			// To discounts
			case 'Avg Disc %':
			case 'Avg Disc $':
			case 'Total % Disc':
			case 'Total Disc $':
				$discounts_by_category[$category][$item] = $value;
			break;
			// to totals
			case 'Items':
			case 'Free Items':
			case 'Reg Total':
			case 'Cur Total':
				$totals_by_category[$category][$item] = $value;
			break;
		}
	}
}

// If your in a browser we need to make sure you know it's preformatted.
if(PHP_SAPI!='cli'){
	echo '<pre>';
}else{
	echo "                          \n";// All those spaces cover the "Processing Page" message.
}

// Display the actual stats
ob_start();
echo 'Steam Pricing Breakdown '.date('F jS, Y g:ia T')."\n";
echo "\n";
echo 'Items Accounted For: '.number_format($total_stats['Items'])."\n";
echo 'Regular Price: '.cash_format($total_stats['Regular Price'])."\n";
echo 'Current Price: '.cash_format($total_stats['Current Price'])."\n";
echo 'Number of Free Items: '.number_format($total_stats['Free Items']).' (%'.round($total_stats['Free Items']/$total_stats['Items'],3).')'."\n";
echo 'Current Number of Discounted Items: '.number_format($total_stats['Disc Items']).' (%'.round($total_stats['Disc Items']/$total_stats['Items'],3).')'."\n";
echo 'Current Savings: %'.round(($total_stats['Regular Price']-$total_stats['Current Price'])/$total_stats['Regular Price'],3).' ('.cash_format($total_stats['Regular Price']-$total_stats['Current Price']).')'."\n";
echo "\n";
echo 'Average Price: '.cash_format($total_stats['Regular Price']/($total_stats['Items']-$total_stats['Free Items'])).' ('.cash_format($total_stats['Regular Price']/$total_stats['Items']).' including free items)'."\n";
echo 'Average Current Price: '.cash_format($total_stats['Current Price']/($total_stats['Items']-$total_stats['Free Items'])).' ('.cash_format($total_stats['Current Price']/$total_stats['Items']).' including free items)'."\n";
echo 'Average Sale Discount: %'.round($total_stats['Total Disc %']/$total_stats['Disc Items'],3).' ('.cash_format(($total_stats['Regular Price']-$total_stats['Current Price'])/$total_stats['Disc Items']).')'."\n";
echo "\n";
new make_ascii_table('Totals By Category',$totals_by_category);
echo "\n";
new make_ascii_table('Discounts By Category',$discounts_by_category);
echo "\n";
new make_ascii_table('By Platform Breakdown',$by_platform);
echo "\n";
new make_ascii_table('By Genre Breakdown',$by_genre);
echo "\n";
echo 'Current Discounts'."\n";
make_ascii_list($discounted_titles);
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
	}
}
		// And a little final cleanup
if(PHP_SAPI!='cli'){
	echo '</pre>';
}else{
	echo "\n";
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

// This is the entire array created during the scrape, it's pretty big and you probably don't care about those details unless your developing something
// print_r($scrape_data);

// This will loop through an array and format the data for display based on column name
function prettify_table($array){
	foreach($array as $row => $data){
		foreach($data as $col => $value){
			switch($col){
				case 'Items':
				case 'Disc Items':
				case 'Free Items':
					$value = number_format($value);
				break;
				case 'Total % Disc':
				case 'Avg Disc %':
					$value = '%'.number_format($value,3);
				break;
				case 'Reg Total':
				case 'Avg $':
				case 'Cur Total':
				case 'Avg Disc $':
				case 'Total Disc $':
					$value = cash_format($value);
				break;
			}
			$array[$row][$col] = $value;
		}
	}
	return $array;
}
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
	return '$'.number_format(round($price,2),2);
}
// Takes an array and turns it into a formatted, monospaced list.
function make_ascii_list($array,$map_array='',$include_total=false){
	// Some basic stuff to make things cleaner
	ksort($array);
	if($include_total){
		$array['Total'] = array_sum($array);
	}
	if($map_array!=''){
		$array=array_map($map_array,$array);
	}
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
// This class takes multi-dimensional arrays and turns them into ascii tabled
class make_ascii_table{
	// These will be filled for you once the class is called
	private $cols = array();
	private $total_cols = 0;
	private $table_width = 0;
	private $title = '';
	private $wrapped = true; // This determines if the table is wrapped
	function __construct($title='',$array){
		// Prep for display
		$this->determine_cols($array);
		$this->wrapped = $wrapped;
		$this->table_width = array_sum($this->cols)+($this->total_cols*3)+1;
		// This is where we start outputting the table.
		$this->output_title($title);
		$this->output_horizontal_line();
		$this->output_header_row();
		$this->output_horizontal_line();
		$this->output_data($array);
		$this->output_horizontal_line();
	}
	// Gets some base information regarding the columns and their contents (lengths mostly);
	function determine_cols($array){
		// This gets the longest length per column
		foreach($array as $g => $data){
			foreach($data as $col => $cell){
				if(!isset($this->cols[$col])){
					$this->cols[$col] = strlen($col);
				}
				if(strlen($cell)>$this->cols[$col]){
					$this->cols[$col] = strlen($cell);
				}
			}
		}
		// This is just the number of columns
		$this->total_cols = count($this->cols);
	}
	// This will output a given array as a line
	function output_row($array,$intersections=false){
		if($intersections){
			echo '+-';
		}else{
			echo '| ';
		}
		$row = 0;
		foreach($array as $col => $value){
			$row++;
			echo $value;			
			if($this->total_cols>$row){
				if($intersections){
					echo '-+-';
				}else{
					echo ' | ';
				}
			}else{
				if($intersections){
					echo '-+';
				}else{
					echo ' |';
				}
				echo "\n";
			}
		}
	}
	// This will output the title if there is one
	function output_title($title){
		if($title!=''){
			$title_lenth = strlen($title);
			$padding = $this->table_width-$title_lenth;
			if($padding<0){
				$padding=0;
			}else{
				$padding = floor($padding/2);
			}
			echo str_repeat(' ',$padding).$title."\n";
		}
	}
	// This outputs a basic horizontal line with appropriate dividers between columns
	function output_horizontal_line(){
		$divider = array();
		foreach($this->cols as $col => $len){
			$divider[$col] = str_repeat('-',$len);
		}
		echo $this->output_row($divider,true);
	}
	// This will output the col names for the header row
	function output_header_row(){
		$header = array();
		foreach($this->cols as $col => $len){
			$header[$col] = sprintf('%-'.$len.'s',$col);
		}
		echo $this->output_row($header);
	}
	// This loops through the data and outputs each row
	function output_data($array){
		foreach($array as $g => $data){
			$row = array();
			foreach($this->cols as $col => $len){
				$row[$col] = sprintf('%-'.$len.'s',$data[$col]);
			}
			echo $this->output_row($row);
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