jQuery(document).ready(function () { 

        //Prepare jtable plugin
        jQuery('#LocationsTableContainer').jtable({
            title: eme.translate_people,
            paging: true,
            sorting: true,
            toolbarsearch: true,
            jqueryuiTheme: true,
            defaultSorting: 'location_id ASC',
            selecting: true, //Enable selecting
            multiselect: true, //Allow multiple selecting
            selectingCheckboxes: true, //Show checkboxes on first column
            selectOnRowClick: true, //Enable this to only select using checkboxes
            toolbar: {
                items: [{
                        text: eme.translate_csv,
                        click: function () {
                                  jtable_csv('#LocationsTableContainer');
                               }
                        },
                        {
                        text: eme.translate_print,
                        click: function () {
                                  jQuery('#LocationsTableContainer').printElement();
                               }
                        }
                        ]
            },
            actions: {
                listAction: ajaxurl+'?action=eme_locations_list'
            },
            fields: {
                location_id: {
                    key: true,
		    title: eme.translate_id,
                    visibility: 'hidden'
                },
                location_name: {
		    title: eme.translate_name
                },
                copy: {
                    title: eme.translate_copy,
                    sorting: false,
                    width: '2%',
                    listClass: 'eme-jtable-center'
                },
                location_address1: {
		    title: eme.translate_address1,
                    visibility: 'hidden'
                },
                location_address2: {
		    title: eme.translate_address2,
                    visibility: 'hidden'
                },
                location_zip: {
		    title: eme.translate_zip,
                    visibility: 'hidden'
                },
                location_city: {
		    title: eme.translate_city,
                    visibility: 'hidden'
                },
                location_state: {
		    title: eme.translate_state,
                    visibility: 'hidden'
                },
                location_country: {
		    title: eme.translate_country,
                    visibility: 'hidden'
                },
                location_longitude: {
		    title: eme.translate_longitude,
                    visibility: 'hidden'
                },
                location_latitude: {
		    title: eme.translate_latitude,
                    visibility: 'hidden'
                }
            }
        });

        // Load list from server, but only if the container is there
        // and only in the initial load we take a possible person id in the url into account
        // This person id can come from the eme_people page when clicking on "view all bookings"
        if (jQuery('#LocationsTableContainer').length) {
           jQuery('#LocationsTableContainer').jtable('load', {
               search_name: jQuery('#search_name').val(),
           });
        }

        // Actions button
        jQuery('#LocationsActionsButton').button().click(function () {
           var selectedRows = jQuery('#LocationsTableContainer').jtable('selectedRows');
           var do_action = jQuery('#eme_admin_action').val();
           var nonce = jQuery('#eme_admin_nonce').val();
           var action_ok=1;
           if (selectedRows.length > 0) {
              if ((do_action=='deleteLocations') && !confirm(eme.translate_areyousuretodeleteselected)) {
                 action_ok=0;
              }
              if (action_ok==1) {
                 var ids = [];
                 selectedRows.each(function () {
                   ids.push(jQuery(this).data('record').location_id);
                 });

                 var idsjoined = ids.join(); //will be such a string '2,5,7'
                 jQuery.post(ajaxurl, {
					"location_id": idsjoined,
					"action": "eme_manage_locations",
					"do_action": do_action,
					"eme_admin_nonce": nonce },
                             function() {
	                        jQuery('#LocationsTableContainer').jtable('reload');
                             });
              }
           } else {
              alert(eme.translate_pleaseselectrecords);
           }
           // return false to make sure the real form doesn't submit
           return false;
        });
 
        // Re-load records when user click 'load records' button.
        jQuery('#LocationsLoadRecordsButton').click(function (e) {
           e.preventDefault();
           jQuery('#LocationsTableContainer').jtable('load', {
               search_name: jQuery('#search_name').val()
           });
           // return false to make sure the real form doesn't submit
           return false;
        });
});
