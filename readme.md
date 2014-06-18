# Steam Costs

Steam Costs is a PHP script, runnable from either the browser or command line, that scrapes the steam store and calculates a total cost for the entire collection, as well as various cost breakdowns that may be of interest. The scraped data and stats are also stored for future use. 

### Setup & Use
Copy `steam-costs.php` to a directory and run, there is no setup involved. When running the script from the command line it will update you with the current page number it is processing. When run from the browser if will appear to hang, but if you wait it will eventually display a stats summary.

To re-run previously scraped data sets you will need to pass the file to the script. In the command line this can be done by send the arg `file=your_scrape_file_here.json`, from the browser this is done by passing the GET variable `?file=your_scrape_file_here.json`. 

Re-processing existing data is obviously much faster than scraping the site, so when adding additional stats or calculating new data sets from the existing data it is highly encouraged that you work of an existing scrape. During times of heavy load, like the ever-popular steam sales, the scrape may fail, if this happens you will have to re-run it as there is currently no convenient way to start from where you left off, additional over-use of this script may get your IP blacklisted by Valve, so keep that in mind when using it.

If you're trying to adjust what data is captured in the scrap I suggest using the preemptive-stop located at line 133 at time of writing, this will stop it after the specified number of pages allowing you much quicker and smaller data sets to test with. Additionally, while doing this you may want to disable the file output located at line 16 at time of writing.

### Example Output
The stats generated will look something like this:

	Steam Pricing Breakdown June 18th, 2014 4:17pm PDT

	Items Accounted For: 6,460
	Total Price: $74,018.58
	Current Price: $72,318.28
	Current Number of Items on Sale: 133
	Current Savings: %0.02297 ($1,700.30)

	Average Price: $11.46
	Average Current Price: $11.19
	Average Sale Discount: %56.526 ($12.78)

	Regular Price Breakdown by Category
	Downloadable Content: $29,666.96
	Games               : $39,964.35
	Software            : $4,387.27

	Regular Price Breakdown by Platform
	Linux    : $8,434.45
	Mac      : $17,946.96
	Steamplay: $18,317.58
	Win      : $73,938.61

	Regular Price Breakdown by Genre
	                     : $10,396.52
	Action               : $25,915.81
	Adventure            : $12,605.45
	Audio Production     : $909.74
	Casual               : $7,095.32
	Early Access         : $3,830.03
	Free To Play         : $6,654.06
	Indie                : $17,190.95
	Massively Multiplayer: $4,164.14
	No Genre Given       : $2,465.27
	RPG                  : $11,480.58
	Racing               : $2,218.66
	Simulation           : $14,648.90
	Sports               : $1,989.35
	Strategy             : $14,150.05
	Utilities            : $629.78

The JSON scrape data will contain a record for each Steam ID found, each record looks something like this:

    "70": {
        "times_seen": 1,
        "name": "Half-Life",
        "category": "Games",
        "price": {
            "current": "9.99",
            "regular": "9.99"
        },
        "platforms": [
            "steamplay",
            "win",
            "mac",
            "linux"
        ],
        "genres": [
            "Action"
        ],
        "release_date": "Nov 8, 1998",
        "metascore": "96"
    },

An example stats and JSON file is included in the repository.

### Known Issues

1. Some items are showing with a blank genre, I have yet to confirm whether this is because they are missing a genre (in which case they should fall under "No Genre Given" or whether the scraper is simply missing their genre).

2. Some items show up multiple times with incorrect values, for example Serious Sam Classic: The First Encounter (Steam ID 41050) and Serious Sam Classic: The Second Encounter (Steam ID 41060) both showing with a $99.99 regular price but in reality are not available outside of collections. Attempts to rectify this have been unsuccessful, but if you have any ideas (outside of a complete site scrape or manually cataloging items that behave this way) then let me know and I'll adjust the scraper accordingly.

3. Some items may not truly fit the category they are in, for example the Steam Greenlight Submission Fee (Steam ID 219820) is listed in the Games category despite the fact that it is a fee. This is because of how steam stores it. There may be additional items that are mis-categorized for one reason or another but outside of manually excluding them I could not determine a way to filter them out.

### Version History
##### 1.0.0
- Initial Release