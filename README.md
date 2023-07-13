# My Sports Plugin

My Sports Plugin is a WordPress plugin for fetching and displaying sports event data and comparing the odds of those events. It uses the [Odds API](https://the-odds-api.com/) to fetch the data, and therefore requires an API key from the Odds API.

## Installation

1. Download the plugin.
2. Navigate to the WordPress plugins page in your admin dashboard.
3. Click on the "Add New" button at the top of the page.
4. Click on the "Upload Plugin" button at the top of the page.
5. Choose the plugin zip file you downloaded and click "Install Now".
6. After installation, click "Activate Plugin".

## Usage

After activation, navigate to the "Odds Event Settings" under the settings tab in your admin dashboard. Here, you can enter your Odds API key, which is necessary for fetching sports data. 

The plugin fetches data from the Odds API and stores it in a WordPress option. The data can then be fetched and used as needed. 

## Endpoints

This plugin registers a custom REST API endpoint at `/wp-json/my-sports-plugin/v1/events/` which returns a list of sports events. 

## Gutenberg Block

This plugin also registers a custom Gutenberg block that can be added to posts or pages. This block allows you to select a sports event and display odds from selected bookmakers.

