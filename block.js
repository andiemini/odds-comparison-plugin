// Immediately Invoked Function Expression (IIFE) to avoid polluting the global namespace
( function( wp ) {
    // Variables for necessary wp libraries
    var el = wp.element.createElement,
        registerBlockType = wp.blocks.registerBlockType,
        SelectControl = wp.blocks.SelectControl;

    // Register new Gutenberg block
    registerBlockType( 'my-sports-plugin/event-selector', {
        // Block's title, icon, and category
        title: 'Event Selector',
        icon: 'megaphone',
        category: 'widgets',

        // Block's attributes
        attributes: {
            selectedEvent: {
                type: 'string',
            },
            selectedBookmakers: {
                type: 'array',
                items: {
                    type: 'string',
                },
            },
        },
        
        // Block's edit function
        edit: function( props ) {
            // props contains attributes and setAttributes function
            var attributes = props.attributes,
                setAttributes = props.setAttributes;

            // Find the selected event in the list of events
            var selectedEvent = window.my_sports_plugin_options.find( function( event ) {
                return event.value === attributes.selectedEvent;
            } );

            // Create options for the bookmakers if an event is selected and it has bookmakers
            var bookmakerOptions = selectedEvent && selectedEvent.bookmakers ? selectedEvent.bookmakers.map( function( bookmaker ) {
                return { value: bookmaker.key, label: bookmaker.name };
            } ) : [];

            // Render select controls for event and bookmakers
            return el( 'div', { className: props.className }, [
                // Event selection control
                el( SelectControl, {
                    label: 'Select Event',
                    value: attributes.selectedEvent,
                    options: window.my_sports_plugin_options || [],
                    onChange: function( newVal ) {
                        setAttributes( { selectedEvent: newVal } );
                    },
                } ),
                // Bookmaker selection control, rendered only if an event is selected
                selectedEvent && el( SelectControl, {
                    label: 'Select Bookmakers',
                    value: attributes.selectedBookmakers,
                    options: bookmakerOptions,
                    multiple: true,
                    onChange: function( newVal ) {
                        setAttributes( { selectedBookmakers: newVal } );
                    },
                } ),
            ] );
        },
        
        // Block's save function. Here, it returns null as the server-side rendering is handled by PHP
        save: function() {
            return null;
        },
    } );
} )( window.wp );
