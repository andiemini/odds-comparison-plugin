<?php
/**
 * Plugin Name: My Sports Plugin
 * Description: A plugin to fetch and display sports event data and comapre odds of those events it uses Odds API to to fetch the data
 * Version: 1.0
 * Author: Andi Emini
 */


/**
 * Add a new options page under the Settings submenu.
 */
 function my_sports_plugin_settings_init() {
    // Register a new setting for "my_sports_plugin" page.
    register_setting( 'my_sports_plugin', 'my_sports_plugin_options', array(
        'type' => 'array',
        'sanitize_callback' => 'my_sports_plugin_options_sanitize',
    ));

    // Register a new section in the "my_sports_plugin" page.
    add_settings_section(
        'my_sports_plugin_section',
        'Odds Event Settings',
        'my_sports_plugin_section_callback',
        'my_sports_plugin'
    );

    // Register new fields in the "my_sports_plugin_section" section, inside the "my_sports_plugin" page.
    add_settings_field(
        'my_sports_plugin_field_api_key',
        'API Key',
        'my_sports_plugin_field_api_key_cb',
        'my_sports_plugin',
        'my_sports_plugin_section'
    );
}

function my_sports_plugin_options_sanitize($input) {
    $output = [];
    $output['api_key'] = sanitize_text_field($input['api_key']);
    return $output;
}

function my_sports_plugin_section_callback() {
    echo '<p>Enter your API key below:</p>';
}


function my_sports_plugin_field_api_key_cb() {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('my_sports_plugin_options');
    // Here, 'api_key' is the name of the form field. We're making it an array so we can have multiple fields.
    ?>
    <input type='text' name='my_sports_plugin_options[api_key]' value='<?php echo $options['api_key']; ?>'>
    <?php
}

// Attach 'my_sports_plugin_menu' to 'admin_menu' action.

add_action('admin_init', 'my_sports_plugin_settings_init');


function my_sports_plugin_menu() {
    add_options_page(
        'Odds Event Settings', // page_title
        'Odds Event Settings', // menu_title
        'manage_options', // capability
        'my_sports_plugin', // menu_slug
        'my_sports_plugin_options_page' // function
    );
}

// Add the options page and menu item.
add_action('admin_menu', 'my_sports_plugin_menu');

/**
 * Render the options page.
 */
function my_sports_plugin_options_page() {
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // show error/update messages
    settings_errors('my_sports_plugin_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "my_sports_plugin"
            settings_fields('my_sports_plugin');
            // output setting sections and their fields for "my_sports_plugin" page
            do_settings_sections('my_sports_plugin');
            // output save settings button
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

//End settings page

/**
 * Fetch data from the sports API and store it as a WordPress option.
 */
function fetch_and_store_sports_data() {
    $options = get_option('my_sports_plugin_options');
    $api_key = $options['api_key'] ?? null;

    // If the API key is not yet set, don't make the request
    if (!$api_key) {
        return;
    }

    $api_url = "https://api.the-odds-api.com/v4/sports/soccer_england_league1/odds/?regions=uk&markets=h2h&apiKey=".$api_key;
    $response = wp_remote_get($api_url);

    if( is_wp_error( $response ) ) {
        error_log('API request error: ' . $response->get_error_message());
        return;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if (!$data) {
        error_log('Failed to decode JSON data from API response.');
        return;
    }

    // Only store the data if it's in the expected format
    if (isset($data[0]['home_team'])) {
        update_option( 'my_sports_plugin_data', $data, false );
    }
}

// Attach 'fetch_and_store_sports_data' to 'init' action.
add_action( 'init', 'fetch_and_store_sports_data' );


/**
 * Callback for a REST API endpoint that returns the events fetched from the API.
 * 
 * @param WP_REST_Request $request Full data about the request.
 * @return WP_REST_Response List of events.
 */

function my_sports_plugin_get_events( $request ) {
    // Fetch the data from the option
    $data = get_option( 'my_sports_plugin_data', [] );

    if (!isset($data[0]['home_team'])) {
        return rest_ensure_response([]);
    }
    // Create an array of events
    $events = array_map( function( $item ) {
        return [
            'id' => $item['id'],
            'home_team' => $item['home_team'],
            'away_team' => $item['away_team'],
            'bookmakers' => array_map( function( $bookmaker ) {
                return [
                    'key' => $bookmaker['key'],
                    'name' => $bookmaker['title'],
                    // Extract markets and outcomes if necessary
                ];
            }, $item['bookmakers'] ),
        ];
    }, $data );
    
    return rest_ensure_response( $events );
}


// Register REST API route for fetching events.

add_action( 'rest_api_init', function () {
    register_rest_route( 'my-sports-plugin/v1', '/events/', array(
        'methods' => 'GET',
        'callback' => 'my_sports_plugin_get_events',
    ) );
} );

/**
 * Register a custom block for Gutenberg editor.
 */

function my_sports_plugin_register_block() {
    wp_register_script(
        'my-sports-plugin-block',
        plugins_url( 'block.js', __FILE__ ),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-i18n'),
        filemtime( plugin_dir_path( __FILE__ ) . 'block.js' )
    );

    register_block_type( 'my-sports-plugin/event-selector', array(
        'api_version' => 2,
        'editor_script' => 'my-sports-plugin-block',
        'render_callback' => 'my_sports_plugin_render_block',
        'attributes' => array(
            'selectedEvent' => array(
                'type' => 'string',
            ),
            'selectedBookmakers' => array(
                'type' => 'array',
                'default' => array(),
                'items'   => [
                    'type' => 'string',
                ],
            ),
        ),
        
    ) );

    wp_enqueue_script(
        'my-sports-plugin-block',
        plugins_url( 'block.js', __FILE__ ),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-i18n'),
        filemtime( plugin_dir_path( __FILE__ ) . 'block.js' )
    );

    $data = get_option( 'my_sports_plugin_data', [] );
 if (!isset($data[0]['home_team'])) {
        return rest_ensure_response([]);
    }
    $options = array_map( function( $item ) {
        return [
            'value' => $item['id'],
            'label' => "{$item['home_team']} vs {$item['away_team']}",
            'bookmakers' => array_map( function( $bookmaker ) {
                return [
                    'key' => $bookmaker['key'],
                    'name' =>$bookmaker['key'],
                ];
            }, $item['bookmakers'] ),
        ];
    }, $data );

    wp_localize_script( 'my-sports-plugin-block', 'my_sports_plugin_options', $options );


}
// Attach 'my_sports_plugin_register_block' to 'init' action.

add_action( 'init', 'my_sports_plugin_register_block' );


/**
 * Render the event selection block with live data.
 * 
 * @param array $attributes Attributes passed from block settings.
 * @return string Rendered block HTML.
 */

function my_sports_plugin_render_block( $attributes ) {
    if ( ! isset( $attributes['selectedEvent'], $attributes['selectedBookmakers'] ) ) {
        return '';
    }

    // Fetch the data from the option
    $data = get_option( 'my_sports_plugin_data', [] );

    // Find the selected event
    $selectedEvent = array_filter($data, function ($event) use ($attributes) {
        return $event['id'] === $attributes['selectedEvent'];
    });

    if ( empty($selectedEvent) ) {
        return '';
    }

    // The selected event is the first (and only) element of the array
    $selectedEvent = reset($selectedEvent);

    // Initialize prices and headers
    $headers = [''];
    $homeTeamPrices = [$selectedEvent['home_team']];
    $awayTeamPrices = [$selectedEvent['away_team']];

    $drawPrices = ['Draw'];

    // For each selected bookmaker
    foreach ( $attributes['selectedBookmakers'] as $selectedBookmakerKey ) {
        // Find the selected bookmaker
        $selectedBookmaker = array_filter($selectedEvent['bookmakers'], function ($bookmaker) use ($selectedBookmakerKey) {
            return $bookmaker['key'] === $selectedBookmakerKey;
        });

        if ( empty($selectedBookmaker) ) {
            continue;
        }

        // The selected bookmaker is the first (and only) element of the array
        $selectedBookmaker = reset($selectedBookmaker);

        // Initialize prices
        $homeTeamPrice = 'N/A';
        $awayTeamPrice = 'N/A';
        $drawPrice = 'N/A';

        // Find the prices for the home team, away team, and draw
        foreach ($selectedBookmaker['markets'] as $market) {
            if ($market['key'] === 'h2h') {
                foreach ($market['outcomes'] as $outcome) {
                    if ($outcome['name'] === $selectedEvent['home_team']) {
                        $homeTeamPrice = $outcome['price'];
                    } elseif ($outcome['name'] === $selectedEvent['away_team']) {
                        $awayTeamPrice = $outcome['price'];
                    } elseif ($outcome['name'] === 'Draw') {
                        $drawPrice = $outcome['price'];
                    }
                }
                break;
            }
        }

        $headers[] = $selectedBookmaker['key'];
        $homeTeamPrices[] = $homeTeamPrice;
        $awayTeamPrices[] = $awayTeamPrice;
        $drawPrices[] = $drawPrice;
    }

    // Build the HTML
    ob_start();
    echo '<table class="sports-event">';
    echo '<thead>';
    echo '<tr><th>' . join('</th><th>', $headers) . '</th></tr>';
    echo '</thead>';
    echo '<tbody>';
    echo '<tr><td>' . join('</td><td>', $homeTeamPrices) . '</td></tr>';
    echo '<tr><td>' . join('</td><td>', $awayTeamPrices) . '</td></tr>';
    echo '<tr><td>' . join('</td><td>', $drawPrices) . '</td></tr>';
    echo '</tbody>';
    echo '</table>';
    
    $html = ob_get_clean();

    return $html;
}
