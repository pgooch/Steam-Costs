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
	Regular Price: $74,018.58
	Current Price: $72,318.28
	Number of Free Items: 310 (%0.048)
	Current Number of Discounted Items: 133 (%0.021)
	Current Savings: %0.023 ($1,700.30)

	Average Price: $12.04 ($11.46 including free items)
	Average Current Price: $11.76 ($11.19 including free items)
	Average Sale Discount: %56.497 ($12.78)

	                                 Totals By Category
	+----------------------+-------+------------+------------+------------+------------+
	| Category             | Items | Disc Items | Free Items | Reg Total  | Cur Total  |
	+----------------------+-------+------------+------------+------------+------------+
	| Games                | 3,327 | 60         | 222        | $39,964.35 | $39,633.36 |
	| Downloadable Content | 3,056 | 65         | 77         | $29,666.96 | $28,570.03 |
	| Software             | 77    | 8          | 11         | $4,387.27  | $4,114.89  |
	+----------------------+-------+------------+------------+------------+------------+

	                                    Discounts By Category
	+----------------------+------------+------------+------------+--------------+--------------+
	| Category             | Disc Items | Avg Disc % | Avg Disc $ | Total % Disc | Total Disc $ |
	+----------------------+------------+------------+------------+--------------+--------------+
	| Games                | 60         | %64.868    | $5.52      | %0.008       | $330.99      |
	| Downloadable Content | 65         | %49.407    | $16.88     | %0.037       | $1,096.93    |
	| Software             | 8          | %51.327    | $34.05     | %0.062       | $272.38      |
	+----------------------+------------+------------+------------+--------------+--------------+

	           By Platform Breakdown
	+-----------+-------+------------+--------+
	| Platform  | Items | Reg Total  | Avg $  |
	+-----------+-------+------------+--------+
	| Win       | 6,457 | $73,938.61 | $11.45 |
	| Steamplay | 1,898 | $18,317.58 | $9.65  |
	| Mac       | 1,856 | $17,946.96 | $9.67  |
	| Linux     | 912   | $8,434.45  | $9.25  |
	+-----------+-------+------------+--------+

	                  By Genre Breakdown
	+-----------------------+-------+------------+--------+
	| Genre                 | Items | Reg Total  | Avg $  |
	+-----------------------+-------+------------+--------+
	|                       | 208   | $10,396.52 | $49.98 |
	| Action                | 2,563 | $25,915.81 | $10.11 |
	| Adventure             | 1,121 | $12,605.45 | $11.24 |
	| Audio Production      | 26    | $909.74    | $34.99 |
	| Casual                | 1,107 | $7,095.32  | $6.41  |
	| Early Access          | 226   | $3,830.03  | $16.95 |
	| Free To Play          | 387   | $6,654.06  | $17.19 |
	| Indie                 | 1,842 | $17,190.95 | $9.33  |
	| Massively Multiplayer | 206   | $4,164.14  | $20.21 |
	| No Genre Given        | 327   | $2,465.27  | $7.54  |
	| RPG                   | 977   | $11,480.58 | $11.75 |
	| Racing                | 200   | $2,218.66  | $11.09 |
	| Simulation            | 1,243 | $14,648.90 | $11.79 |
	| Sports                | 137   | $1,989.35  | $14.52 |
	| Strategy              | 1,578 | $14,150.05 | $8.97  |
	| Utilities             | 22    | $629.78    | $28.63 |
	+-----------------------+-------+------------+--------+

	Current Discounts
	3DMark                                                                 : %40 Off (Was: $24.99 Now: $10.00)
	A Wizard's Lizard                                                      : %66 Off (Was: $14.99 Now: $9.89)
	Abyss Odyssey                                                          : %67 Off (Was: $14.99 Now: $9.99)
	Aliens: Colonial Marines                                               : %25 Off (Was: $19.99 Now: $4.99)
	...

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

##### 1.1.0
- Changed the way the summary was displayed, now broken up by category into tables. 
- Added a list of currently discounted items to the summary display.
- Updated the example data set, the new one is from the 2014 Summer Sale.