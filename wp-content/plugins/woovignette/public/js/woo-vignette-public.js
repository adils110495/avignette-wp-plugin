(function ($) {
    'use strict';

    /**
     * All of the code for your public-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here, so the
     * $ function reference has been prepared for usage within the scope
     * of this function.
     *
     * This enables you to define handlers, for when the DOM is ready:
     *
     * $(function() {
     *
     * });
     *
     * When the window is loaded:
     *
     * $( window ).load(function() {
     *
     * });
     *
     * ...and/or other possibilities.
     *
     * Ideally, it is not considered best practise to attach more than a
     * single DOM-ready or window-load handler for a particular page.
     * Although scripts in the WordPress core, Plugins and Themes may be
     * practising this, we should strive to set a better example in our own work.
     */
        let map;
        let directionsService;
        let directionsRenderer;
        let originInput;
        let destinationInput;
        let originMarker;
        let destinationMarker;
        let countries = [];
        let countryGeoJson;
        let infowindow;
        let defaultCenter = {lat:48.2081,lng:6.3713}

        // Fix 3: Lazy-load country data only when the route planner is actually used.
        let countryDataPromise = null;
        function loadCountryData() {
            if ( countryGeoJson ) {
                return Promise.resolve( countryGeoJson );
            }
            if ( ! countryDataPromise ) {
                countryDataPromise = fetch( wv_settings.country_data, { cache: 'force-cache' } )
                    .then( function( response ) { return response.json(); } )
                    .then( function( data ) { countryGeoJson = data; return data; } );
            }
            return countryDataPromise;
        }
        function initAutocomplete(){
            originInput = document.getElementById("departure");
            destinationInput = document.getElementById("destination");
            let originOutput = new google.maps.places.Autocomplete(originInput);
            originOutput.addListener("place_changed", function () {
               const place = originOutput.getPlace();
            
               if (!place.geometry) {
                 console.log("No details available for the selected place.");
                 return;
               }
                $("#departure").val(place.formatted_address)
             });
            let destinationOutput = new google.maps.places.Autocomplete(destinationInput);
            destinationOutput.addListener("place_changed", function () {
               const place = destinationOutput.getPlace();
            
               if (!place.geometry) {
                 console.log("No details available for the selected place.");
                 return;
               }
                $("#destination").val(place.formatted_address)
             });
        }
        /* function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 4,
                center: defaultCenter,
            });
            
            infowindow = new google.maps.InfoWindow();
        } */
        $(window).load(function(){
            initAutocomplete()
            let map_sidebar = $(".map-sidebar")
            $(".map-sidebar").remove()
            $(".map-conatiner").prepend(map_sidebar);
            let sidebar_content = $("#map-sidebar-content").html();
            $("#map-sidebar-content").remove();
            $(".map-sidebar").find(".uagb-popup-builder__container").html(sidebar_content);
            // Country data is now loaded lazily in loadCountryData() on first route plan.
        })
        function draggableService() {
            // Fix 3: Start loading country data now (671 KB) — it will be ready by the
            // time the Directions API responds and processRoute() is called.
            loadCountryData().catch( function( error ) {
                $("#map-error #message").text("Error loading map data");
                console.error('Error loading GeoJSON file:', error);
            });

            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 4,
                center: defaultCenter,
            });
            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer({
                draggable: true,
                map
            });

            directionsRenderer.addListener("directions_changed", () => {
                const directions = directionsRenderer.getDirections();

                if (directions) {
                    computeTotalDistance(directions);
                }
            });
            const origin = originInput.value;
            const destination = destinationInput.value;

            if (!origin || !destination) {
                $("#map-error #message").text("Please enter both origin and destination.")
                return;
            }
            displayRoute(
                origin,
                destination,
                directionsService,
                directionsRenderer,
            );
        }
        
        function displayRoute(origin, destination, service, display) {
            service
                .route({
                    origin: origin,
                    destination: destination,
                    travelMode: google.maps.TravelMode.DRIVING,
                    optimizeWaypoints: true,
                    avoidTolls: false,
                })
                .then((result) => {
                    display.setDirections(result);
                })
                .catch((e) => {
                    $("#map-error #message").text("Could not display directions due to: " + e)
                    console.log("Could not display directions due to: " + e)
                });
        }
        function computeTotalDistance(response) {
            const leg = response.routes[0].legs[0];
            const distance = leg.distance.text;
            const duration = leg.duration.text
            document.getElementById("distance").innerText = distance;
            document.getElementById("wv-distance").value = distance;
            document.getElementById("duration").innerText = "Duration: " + duration;
            document.getElementById("wv-duration").value = stringToDay(duration);
            const route = response.routes[0];
            processRoute(route);
            //displayCountries(Array.from(countriesSet));
        }
        
        function processRoute(route) {
            if ( !countryGeoJson ) return; // guard against data not yet loaded
        
            const tollsByCountry = {};
            const countryPolygons = {};
        
            // Step 1: Create Google Maps Polygons for each country's border using coordinates
            countryGeoJson.features.forEach(countryData => {
                const countryCode = countryData.properties.code;
                const coordinates = countryData.geometry.coordinates;
                countryPolygons[countryCode] = new google.maps.Polygon({paths: coordinates});
            });
            
            let countriesSet = new Set();
            // Step 3: Loop through each leg and each step in the directions
            route.legs.forEach(leg => {
                leg.steps.forEach(step => {
                    // Points of interest: start_location and end_location of each step
                    const locations = [step.start_location, step.end_location];
        
                    locations.forEach(location => {
        
                        // Check if the current location is inside any country polygon
                        for (const [countryCode, polygon] of Object.entries(countryPolygons)) {
                                /*if (countriesSet.has(countryCode)) {
                                    continue;
                                }*/
                                if (google.maps.geometry.poly.containsLocation(location, polygon)) {
                                    
                                    // Add the country to the traversed list if it's not already included
                                    if (countryGeoJson.tolls[countryCode]) {
                                        tollsByCountry[countryCode] = tollsByCountry[countryCode] || {};
        
                                        for (const toll of countryGeoJson.tolls[countryCode]) {
                                            if (tollsByCountry[countryCode][toll.reference]) continue;
        
                                            let isWithinRadius = false;
                                            for (const point of toll.lat_lngs) {
                                                isWithinRadius = point_in_radius(
                                                    step.path,
                                                    new google.maps.LatLng(point.lat, point.lng),
                                                    point.radius_km
                                                );
                                                if (isWithinRadius) break;
                                            }
        
                                            if (isWithinRadius) {
                                                tollsByCountry[countryCode][toll.reference] = toll;
                                            }
                                        }
                                    }
                                    countriesSet.add(countryCode);
                                    break; // Once we find the country, no need to check further
                                }
                        }
                    });
                });
            });
            // Step 4: Process the traversed countries and toll information
            processTraversedCountries(countriesSet, tollsByCountry);
        }

        function point_in_radius(path, point, radiusKm) {
            const radiusMeters = radiusKm * 1000; // Convert kilometers to meters
        
            // Check each point in the path
            for (const pathPoint of path) {
                const distance = google.maps.geometry.spherical.computeDistanceBetween(pathPoint, point);
                if (distance <= radiusMeters) {
                    return true; // Point is within the radius
                }
            }
        
            return false; // No points are within the radius
        }

        function processTraversedCountries(countries, tolls) {
            const countryList = Array.from(countries); // Convert Set to Array
            const lastCountry = countryList[countryList.length - 1];
            const filterCountry = countryList;
           
            let results = [];
            results['countries'] = countryGeoJson.features
                .map(countryData => {
                    if (filterCountry.some(country => country === countryData.properties.code)) {
                        return {
                            code: countryData.properties.code,
                            name: countryData.properties.name
                        };
                    }
                    return null; // To handle cases where no match is found
                })
                .filter(Boolean); // Remove null values from the result
        
            results['tolls'] = tolls;
            displayTollAndCountries(results);
        }

        
        function displayTollAndCountries(result) {
            let countries = result['countries']; 
            let tolls = result['tolls']; 
            let countryInput = [];
            let tollInput = [];
            let vignetteName = wv_settings?.product_name?.vignette ??{}
            let tollName = wv_settings?.product_name?.toll ??{}
            // Handle countries
            if (countries.length > 0) {
                $(".vignette-section").addClass("active");
                let list = "<ul>";
                
                countries.forEach((country, index) => { // Swapped parameters
                    countryInput.push(country.code)
                    let countryName = vignetteName[country.code] != undefined ? vignetteName[country.code] : country.name;
                    list += `<li>${countryName}</li>`;
                });
                
                list += "</ul>";
                $("#vignette-list").html(list);
                $("#wv-countries").val(countryInput.join(","));
            } else {
                $(".vignette-section").removeClass("active");
            }
            // Convert dynamic keys to a tolls array
            let latlongData = [];
            const tollData = [];
            let tollCounter = 1;
            for (const countryKey in tolls) {
                if (tolls.hasOwnProperty(countryKey)) {
                    const countryTolls = tolls[countryKey];
                    for (const tollKey in countryTolls) {
                        if (countryTolls.hasOwnProperty(tollKey)) {
                            const toll = countryTolls[tollKey];
                            tollData.push(countryTolls[tollKey]);
                            const markerData = toll.marker;
                            let markerLatLng = markerData.lat_lng//new google.maps.LatLng(markerData.lat, markerData.lng)
                            console.log({markerLatLng})
                            createMarker(markerLatLng,markerData.title)
                        }
                    }
                }
            }

            // Handle tolls
            if (tollData.length > 0) {
                $(".toll-section").addClass("active");
                let list = "<ul>";
                
                tollData.forEach((toll, index) => { // Swapped parameters
                    console.log(toll);
                    tollInput.push(toll.reference)
                    let toll_title = tollName[toll.reference] != undefined?tollName[toll.reference]: toll.marker.title;
                    list += `<li>${toll_title}</li>`;
                });
                
                list += "</ul>";
                $("#toll-list").html(list);
                $("#wv-tolls").val(tollInput.join(","));
            } else {
                $(".toll-section").removeClass("active");
            }
            $(".map-sidebar").addClass("active")
            $("#map-error #message").text("")
        }

        function createMarker(latlng, html) {
            const image = {
                                url: wv_settings.toll_icon,
                                size: new google.maps.Size(32, 32),  // Original size
                                scaledSize: new google.maps.Size(48, 48), // Larger marker size
                                origin: new google.maps.Point(0, 0),
                                anchor: new google.maps.Point(24, 24) // Anchor at the center of the image
                            };
            const marker = new google.maps.Marker({
                position: latlng,
                map: map,
                title: html,
                optimized: true,
                icon: image
            });
            //marker.setMap(map);
            // Allow each marker to have an info window
              google.maps.event.addListener(marker, 'click', function() {
                infowindow.setContent(html);
                infowindow.open(map, marker);
              });
              //return marker;
        }
            
        function stringToDay(timeString) {
            // Regular expression to match the pattern of "X day(s)", "Y hour(s)", and "Z minute(s)"
            const regex = /(\d+)\s*(day|hour|minute)s?/g;
            let totalDays = 0;
        
            // Loop through the matches and accumulate the day count
            timeString.replace(regex, function(match, value, unit) {
                value = parseInt(value);
                if (unit === "day") {
                    totalDays += value;
                } else if (unit === "hour") {
                    totalDays += value / 24; // Convert hours to days
                } else if (unit === "minute") {
                    totalDays += value / (24 * 60); // Convert minutes to days
                }
            });
        
            // Return the total days as a string
            return totalDays ? totalDays.toFixed(2):1;
        }    
    
        $(function(){
            let is_checkout_page = $("body").hasClass("woocommerce-checkout");
            if( !is_checkout_page ){
                is_checkout_page = $("body").hasClass("ast-modern-checkout");
            }

            let originalEvent = null;
            
            function initializeDatePicker(selector) {
                $(selector).each(function() {
                    var input = $(this);
                    input.attr('autocomplete', 'off');
                    var minDateStr = input.data('min-date');
                    var minDate = minDateStr ? new Date(minDateStr) : null;
                    if (minDate) { minDate.setHours(0, 0, 0, 0); }

                    input.off('click.datepicker').on('click.datepicker', function() {
                        var val = input.val();
                        var opts = {
                            views: {
                                month: {
                                    show: val || null,
                                    selected: val ? [val] : [],
                                    firstDayOfWeek: 1
                                }
                            },
                            element: input
                        };
                        if (minDate) {
                            opts.restrictDates = 'custom';
                            opts.callbacks = {
                                onCheckCell: function(cell, date, view) {
                                    return date >= minDate;
                                }
                            };
                        }
                        var widget = $.datePicker.api.show(opts);
                        input.data('widget', widget);
                    });
                });
            }

            // Init dd.mm.yyyy datepicker on checkout date fields (no min date — registration date is in the past)
            if (is_checkout_page) {
                initializeDatePicker('.wv-checkout-datepicker');
                // Re-init after WooCommerce refreshes the checkout fragments
                $(document.body).on('updated_checkout', function() {
                    initializeDatePicker('.wv-checkout-datepicker');
                });
            }

        $(document).on("change", ".wv-country", function (event) {
            let wv_countries = $(this).val();
            wv_countries = wv_countries.join(",");
            fetchVariations({ wv_countries });
        });

        $(document).on("click", ".variation-input", function (event) {
            let parent_div = $(this).closest(".wv-variation-option");
            if( $(this).data("description") ){
                parent_div.find(".wv-variation-description").text($(this).data("description"));
            }
            if (parent_div.is(".wv-variation-option:last")) {
                return;
            }
            let wv_countries = $("#wv-country").val();
            if (Array.isArray(wv_countries)) {
                wv_countries = wv_countries.join(","); // If wv_countries is an array, join them with commas
            }

            let data = { wv_countries }; // Create an object with the wv_countries value

            // Iterate through each variation option
            $(".wv-variation-option").each(function () {
                // Find the first input in each variation option and get its name
                let variation_name = $(this).find("input:first").attr("name");
                let variation_value = $("input[name='" + variation_name + "']:checked").val();
                if (variation_name && variation_value != "undefined" && $("input[name='" + variation_name + "']").is(":checked")) {
                    // Get the value of the input field for that variation name and add it to the data object
                    data[variation_name] = $("input[name='" + variation_name + "']:checked").val();
                }
            });

            // Call the function to fetch variations
            fetchVariations(data);
        });


        $(document).on("click", ".wv-submit", function (e) {
            originalEvent = e
            e.preventDefault();
            if (!validateForm()) {
                return;
            }
            $.ajax({
                type: $("#wv-order-form").attr("method"),
                url: wv_settings.ajax_url,
                data: $("#wv-order-form").serialize(),
                dataType: "json",
                beforeSend: function () {
                    showLoader( '.uagb-popup-builder__wrapper--popup' )
                },
                success: function (res) {
                    if (res.success) {
                        loadMiniCart();
                    } else {
                        $('#error-message').text(res.data.message);
                        hideLoader();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    $('#error-message').text("something went wrong");
                    hideLoader();
                },
                complete: function () {
                    hideLoader();
                }
            });
        });

        function fetchVariations(data) {
            data.action = 'get_variations'
            $.ajax({
                url: wv_settings.ajax_url,
                data: data,
                dataType: "json",
                beforeSend: function () {
                    showLoader( '.uagb-popup-builder__wrapper--popup' )
                },
                success: function (res) {
                    if (res.success) {
                        $("#wv-variations").html(res.data);
                    } else {
                        $('#error-message').text(res.data.message);
                    }
                    hideLoader();
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.log('Status:', status, 'XHR:', xhr);
                    $('#error-message').text("something went wrong");
                    hideLoader();

                },
                complete: function () {
                    hideLoader();
                }
            });
        }

        function validateForm() {
            let form = $("#wv-order-form");
            let isValid = true;
            let errorMessage = '';
            $(".wv-error").removeClass("wv-error");
            // Clear previous error message
            $('#error-message').text('');

            // Loop through all required fields
            form.find('[required]').each(function () {
                let $this = $(this);

                // Check for text, email, or number inputs
                if ($this.is('input[type="text"], input[type="email"], input[type="number"],input[type="date"]')) {
                    if ($this.val() === '') {
                        isValid = false;
                        $this.addClass("wv-error"); // Highlight empty fields
                    } else {
                        $this.removeClass("wv-error"); // Reset border style
                    }
                }

                // Check for radio buttons (ensure one is selected)
                else if ($this.is('input[type="radio"]')) {
                    let radioName = $this.attr('name');
                    if ($('input[name="' + radioName + '"]:checked').length === 0) {
                        isValid = false;
                        $this.closest(".wv-variation-option").find(".variation-label").addClass("wv-error"); // Highlight empty fields
                    } else {
                        $this.closest(".wv-variation-option").find(".variation-label").removeClass("wv-error"); // Reset border style
                    }
                }
                // Check for select fields
                else if ($this.is('select')) {
                    if ($this.val().length === 0) {
                        isValid = false;
                        $this.closest("div").find(".select2-container").addClass("wv-error"); // Highlight empty select
                    } else {
                        $this.closest("div").find(".select2-selection").removeClass("wv-error"); // Reset border style
                    }
                }

            });

            if (isValid) {
                $('#error-message').text("");
            } else {
                errorMessage = 'Please fill out all required fields.'
                $('#error-message').text(errorMessage).css('color', 'red'); // Show error message
            }
            return isValid;
        }

        $(document).on("click", ".vignette-delete", function (e) {
            originalEvent = e
            e.preventDefault();
            let item_key = $(this).data("item_key");
            let start_date = $(this).data("start");
            let end_date = $(this).data("end");
            let target = $(this).closest(".vignette-item");
            let isMiniCart = $(this).closest(".order-form-popup-sidebar").length > 0
            $.ajax({
                url: wv_settings.ajax_url,
                data: {
                    action: "wv_delete_vignette",
                    item_key,
                    start_date,
                    end_date
                },
                dataType: "json",
                beforeSend: function () {
                    showLoader( '.uagb-popup-builder__wrapper--popup' )
                },
                success: function (res) {
                    if (res.success) {
                        if( isMiniCart ){
                            if ( res.data && res.data.mini_cart_html ) {
                                // Inject updated cart HTML directly — eliminates the second AJAX round-trip.
                                $(".order-form-popup-sidebar .uagb-popup-builder__container").html(res.data.mini_cart_html);
                            } else {
                                $("a[href='#Cart']").trigger("click");
                            }
                        }
                        if( is_checkout_page ){
                            jQuery(document.body).trigger("update_checkout")
                        }
                    } else {
                        $('#error-message').text(res.data.message);
                    }
                    hideLoader()
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.log('Status:', status, 'XHR:', xhr);
                    $('#error-message').text("something went wrong");
                    hideLoader()
                },
                complete: function () {
                    hideLoader()
                }
            });

        });

        $("#route-planner-submit").click(function (e) {
            e.preventDefault();
            const departure = $('#departure').val();
            const destination = $('#destination').val();
            if (departure && destination) {
                draggableService();
                const formData = $("#wv-route-planner-form").serialize();
                $('#error-message').text(" ");
            } else {
                var errorMessage = 'Please fill out all required fields.'
                $('#error-message').text(errorMessage).css('color', 'red');
            }
        });

        jQuery('.wv-country').select2({
            placeholder: "Select Your Countries",
            allowClear: true,
            dropdownPosition: 'below'
        });

        $(document).on("click", "#show-specification", function (event) {
            if ($(this).find("i").hasClass("dashicons-arrow-down-alt")) {
                $(this).find("i").removeClass("dashicons-arrow-down-alt").addClass("dashicons-arrow-up-alt");
                var $cell = $(".specification-data-cell");
                if (!$cell.data("rendered")) {
                    var specs = $(this).data("specifications") || [];
                    var html = '<table class="wv-spec-table" style="width:100%"><tbody>';
                    $.each(specs, function (i, s) {
                        html += '<tr><td>' + s.name + ' x' + s.quantity + '</td><td>' + s.line_total + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    $cell.html(html).data("rendered", true);
                }
                $(".specification-data").show();
            } else {
                $(this).find("i").removeClass("dashicons-arrow-up-alt").addClass("dashicons-arrow-down-alt");
                $(".specification-data").hide();
            }
        })

        $(document).on("click", "#optional-product", function (event) {
            let isMiniCart = $(this).closest(".order-form-popup-sidebar").length > 0
            //event.preventDefault();
            var quantity = 1;
            var productID = $(this).data('product_id');  // Get the product ID
            var cartItemKey = $(this).data('cart_item_key');
            if ($(this).is(":checked")) {
                add_optional_product(productID, quantity, isMiniCart)
            } else {
                remove_optional_product(productID, cartItemKey, isMiniCart)
            }
        })

        function add_optional_product(productID, quantity, isMiniCart = false) {
            let loaderSelector = isMiniCart
                ? '.order-form-popup-sidebar .uagb-popup-builder__wrapper--popup'
                : '.uagb-popup-builder__wrapper--popup';
            showLoader(loaderSelector);
            $.ajax({
                url: wv_settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'wv_add_to_cart_optional_product',
                    nonce: wv_settings.add_to_cart_nonce,
                    product_id: productID,
                    quantity: quantity
                },
                success: function (response) {
                    if (response.success) {
                        if( isMiniCart ){
                            $("a[href='#Cart']").trigger("click");
                        }
                        if( is_checkout_page ){
                            jQuery(document.body).trigger("update_checkout")
                        }
                    } else {
                        console.log(response.data.message);
                    }
                },
                error: function() {
                    hideLoader();
                },
                complete: function() {
                    hideLoader();
                }
            });
        }

        function remove_optional_product(productID, cartItemKey, isMiniCart = false) {
            let loaderSelector = isMiniCart
                ? '.order-form-popup-sidebar .uagb-popup-builder__wrapper--popup'
                : '.uagb-popup-builder__wrapper--popup';
            showLoader(loaderSelector);
            $.ajax({
                url: wv_settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'wv_remove_optional_product',
                    nonce: wv_settings.remove_optional_product_nonce,
                    product_id: productID,
                    cart_item_key: cartItemKey
                },
                success: function (response) {
                    if (response.success) {
                        if( isMiniCart ){
                            $("a[href='#Cart']").trigger("click");
                        }
                        if( is_checkout_page ){
                            jQuery(document.body).trigger("update_checkout")
                        }
                    } else {
                        console.log(response.data.message);
                    }
                },
                error: function() {
                    hideLoader();
                },
                complete: function() {
                    hideLoader();
                }
            });
        }

        /*$(document).on("click",".sidebar-popup-order-form",function (event) {
            event.preventDefault()
            
            open_order_form(formdata,popup_width);
        });*/
        
        $(document).on("click","a[href^='#order-form']",function (event) {
            event.preventDefault()
            var hasForm = $(this).closest('#map-order-form').length > 0;
            let href = $(this).attr('href');
            let formdata = {};
            let popup_width = $(this).data("width") || '80%'
            if( hasForm ){
                let mapForm = $(this).closest("#map-order-form")[0];
                const formData = new FormData(mapForm);
                // Iterate over each entry in the FormData
                formData.forEach((value, key) => {
                  formdata[key] = value;
                })
            }
            if (href.includes('&lang=')) {
                let params = new URLSearchParams(href.split('&')[1]);
                formdata.countries = params.get('lang');
            }
            open_order_form(formdata,popup_width);
        });
        
        function open_order_form( formData = {}, popup_width = '80%' ){
            let popup_sidebar = $(".order-form-popup-sidebar")
            let popup_sidebar_wrapper = $(".order-form-popup-sidebar .uagb-popup-builder__wrapper--popup")
            let popup_sidebar_container = $(".order-form-popup-sidebar .uagb-popup-builder__container")
            //$("#order-form-popup-sidebar .order-form-popup-content").html("");
            //$("#order-form-popup-sidebar").addClass('active').css('width', popup_width);
            
            popup_sidebar.addClass('active');
            popup_sidebar_wrapper.css("width",popup_width);
            popup_sidebar_container.html("");
            let data = {action:"wv_show_sidebar_popup_order_form", ...formData}
            $.ajax({
                url: wv_settings.ajax_url,
                type: 'POST',
                data: data,
                dataType: "json",
                beforeSend: function () {
                    showLoader( ".order-form-popup-sidebar .uagb-popup-builder__wrapper--popup" )
                },
                success: function (res) {

                    if (res.success) {
                        //$("#order-form-popup-sidebar .order-form-popup-content").html(res.data.html);
                        popup_sidebar_container.html(res.data.html);
                        $(".wv-country").select2();
                        fetchVariations({wv_countries:formData.countries});
                        initializeDatePicker('.wv-datepicker');
                    } else {
                        //$("#order-form-popup-sidebar .order-form-popup-content").html('<p>Something went wrong. Please try again.</p>');
                        console.log(res.data.message);
                    }
                    hideLoader();
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    hideLoader();
                },
                complete: function () {
                    hideLoader();
                }
            });
        }
        $(document).on("click",".uagb-popup-builder__close",function (event) {
            $(this).closest(".wp-block-uagb-popup-builder").removeClass("active")
            //$("#order-form-popup-sidebar").removeClass("active");
        });

        $(document).on("keydown", function (e) {
            if (e.key === "Escape") {
                $("#order-form-popup-close").trigger("click");
            }
        });

        function loadMiniCart( popup_width ) {
            let popup_sidebar = $(".order-form-popup-sidebar");
            let popup_sidebar_wrapper = $(".order-form-popup-sidebar .uagb-popup-builder__wrapper--popup");
            let popup_sidebar_container = $(".order-form-popup-sidebar .uagb-popup-builder__container");
            popup_width = popup_width || '50%';
            popup_sidebar_container.html("");
            popup_sidebar.addClass('active');
            popup_sidebar_wrapper.css("width", popup_width);
            $.ajax({
                url: wv_settings.ajax_url,
                type: 'POST',
                data: { action: "wv_show_minicart_form" },
                dataType: "json",
                beforeSend: function () {
                    showLoader('.uagb-popup-builder__wrapper--popup');
                },
                success: function (res) {
                    if (res.success) {
                        popup_sidebar_container.html(res.data.html);
                        if( is_checkout_page ){
                            jQuery(document.body).trigger("wc_update_cart");
                        }
                    } else {
                        popup_sidebar_container.html(res.data.message);
                    }
                    hideLoader();
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    hideLoader();
                },
                complete: function () {
                    hideLoader();
                }
            });
        }

        $(document).on("click","a[href='#Cart']",function (event) {
            event.preventDefault();
            let originalEvent = event.originalEvent;
            let skipClear = false;
            if( originalEvent ){
                let targetClass = originalEvent.target.classList;
                if( targetClass.contains('vignette-delete') ){
                    skipClear = true;
                }
            }
            let popup_width = $(this).data("width") || '50%';
            if( !skipClear ){
                $(".order-form-popup-sidebar .uagb-popup-builder__container").html("");
            }
            loadMiniCart(popup_width);
        });
        $("#order-form-popup-close").click(function () {
            $("#order-form-popup-sidebar").removeClass("active");
        });

        $(document).on("keydown", function (e) {
            if (e.key === "Escape") {
                $("#order-form-popup-close").trigger("click");
            }
        });
        $(document).on("click","#onloadPopup #popup-close",function(){
             $("#onloadPopup").hide()
        });
        $('form.checkout').on('click',"#place_order", function(event) {
            event.preventDefault();
            var fileInput = $('#vehicle_scan_registration_document'); // The file input field
            var formData = new FormData($( 'form.checkout' )[0]); // Capture the entire form's data

        // If the file input is not empty, upload the file via AJAX
        if (fileInput.length > 0 && fileInput[0] && fileInput[0].files.length > 0) {

            // Prepare the AJAX request
            formData.append('action', 'wv_checkout_file_upload');
            
            $.ajax({
                url: wv_settings.ajax_url, // WordPress AJAX URL (should be defined in wp_localize_script)
                type: 'POST',
                data: formData,
                contentType: false,  // Let the browser determine the content type
                processData: false,  // Do not process the data
                beforeSend: function() {
                    // Optionally show a loading spinner here
                    $('#upload_message').html('Uploading file...');
                },
                success: function(response) {
                    if (response.success) {
                        $('#document_url').val(response.data.file_url);
                        setTimeout(function(){
                            $( 'form.checkout' ).trigger("submit");
                        },500);
                    } else {
                        console.log("Error: " + response.message);
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.log("AJAX error:" + errorThrown);
                }
            });
        } else {
            // If file input does not exist or is empty, continue with form submission
            setTimeout(function(){
                $('form.checkout').trigger("submit");
            }, 500);
            }
        });
        // Compute the minimum selectable date from the admin setting.
        function getMinDate() {
            const days = parseInt(wv_settings.advance_min_days, 10) || 0;
            const d = new Date();
            d.setDate(d.getDate() + days);
            return d.toISOString().split('T')[0]; // YYYY-MM-DD
        }

        // Enforce min on any date input injected via AJAX.
        $(document).on("focus", "input[type='date']", function () {
            if (!this.min) {
                this.min = getMinDate();
            }
        });

        // When start date changes: clear invalid value and push end date min forward.
        $(document).on("change", "#start-date", function () {
            const startVal = this.value;
            const minDate  = getMinDate();

            if (startVal && startVal < minDate) {
                this.value = minDate;
            }

            const endInput = document.getElementById('end-date');
            if (endInput) {
                // End date must be >= selected start date.
                const newMin = this.value || minDate;
                endInput.min = newMin;
                if (endInput.value && endInput.value < newMin) {
                    endInput.value = newMin;
                }
            }
        });

        $(document).on("focus click", "input[type='date']", function (event) {
            if (event.type === "click" && this === document.activeElement) {
                // If the input is already focused, show the date picker on click
                this.showPicker();
            }
        });
        $(document).on("blur", "input[type='date']", function () {
            const value = this.value;
            const isValidDate = /^\d{4}-\d{2}-\d{2}$/.test(value);
            if (!isValidDate && value) {
                this.value = ""; // Clear invalid input
            }
        });
        
        function showLoader( selector ){
            $("#wv-loader").remove();
            let loader = '<div id="wv-loader"></div>';
            $(selector).prepend(loader);
        }
        
        function hideLoader(){
            $("#wv-loader").remove();
        }
        
    });

})(jQuery);