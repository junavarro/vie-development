function updateIntervalDescriptor () { 
   jQuery(".interval-desc").hide();
   var number = "-plural";
   if (jQuery('input#recurrence-interval').val() == 1 || jQuery('input#recurrence-interval').val() == "") {
      number = "-singular";
   }
   var descriptor = "span#interval-"+jQuery("select#recurrence-frequency").val()+number;
   jQuery(descriptor).show();
}

function updateIntervalSelectors () {
   jQuery('span.alternate-selector').hide();
   jQuery('span#'+ jQuery('select#recurrence-frequency').val() + "-selector").show();
   //jQuery('p.recurrence-tip').hide();
   //jQuery('p#'+ jQuery(this).val() + "-tip").show();
}

function updateShowHideRecurrence () {
   if(jQuery('input#event-recurrence').attr("checked")) {
      jQuery("#event_recurrence_pattern").fadeIn();
      jQuery("span#event-date-recursive-explanation").show();
      jQuery("div#div_recurrence_date").show();
   } else {
      jQuery("#event_recurrence_pattern").hide();
      jQuery("span#event-date-recursive-explanation").hide();
      jQuery("div#div_recurrence_date").hide();
   }
}

function updateShowHideRecurrenceSpecificDays () {
   if (jQuery('select#recurrence-frequency').val() == "specific") {
      jQuery("div#recurrence-intervals").hide();
      jQuery("input#localized-rec-end-date").hide();
      jQuery("p#recurrence-dates-explanation").hide();
      jQuery("span#recurrence-dates-explanation-specificdates").show();
      jQuery("#localized-rec-start-date").datepick('option','multiSelect',999);
   } else {
      jQuery("div#recurrence-intervals").show();
      jQuery("input#localized-rec-end-date").show();
      jQuery("p#recurrence-dates-explanation").show();
      jQuery("span#recurrence-dates-explanation-specificdates").hide();
      jQuery("#localized-rec-start-date").datepick('option','multiSelect',0);
   }
}

function updateShowHideRsvp () {
   if (jQuery('input#rsvp-checkbox').attr("checked")) {
      jQuery("div#div_event_rsvp").fadeIn();
      jQuery("#event-tabs").tabs("enable","#tab-mailformats");
      jQuery("div#div_event_contactperson_email_body").fadeIn();
      jQuery("div#div_event_registration_recorded_ok_html").fadeIn();
      jQuery("div#div_event_respondent_email_body").fadeIn();
      jQuery("div#div_event_registration_pending_email_body").fadeIn();
      jQuery("div#div_event_registration_updated_email_body").fadeIn();
      jQuery("div#div_event_registration_form_format").fadeIn();
      jQuery("div#div_event_cancel_form_format").fadeIn();
      jQuery("div#div_event_registration_cancelled_email_body").fadeIn();
      jQuery("div#div_event_registration_denied_email_body").fadeIn();
   } else {
      jQuery("div#div_event_rsvp").fadeOut();
      jQuery("#event-tabs").tabs("disable","#tab-mailformats");
      jQuery("div#div_event_contactperson_email_body").fadeOut();
      jQuery("div#div_event_registration_recorded_ok_html").fadeOut();
      jQuery("div#div_event_respondent_email_body").fadeOut();
      jQuery("div#div_event_registration_pending_email_body").fadeOut();
      jQuery("div#div_event_registration_updated_email_body").fadeOut();
      jQuery("div#div_event_registration_form_format").fadeOut();
      jQuery("div#div_event_cancel_form_format").fadeOut();
      jQuery("div#div_event_registration_cancelled_email_body").fadeOut();
      jQuery("div#div_event_registration_denied_email_body").fadeOut();
   }
}

function updateShowHideTime () {
   if (jQuery('input#eme_prop_all_day').attr("checked")) {
      jQuery("div#time-selector").hide();
   } else {
      jQuery("div#time-selector").show();
   }
}

function eme_event_location_autocomplete () {
    // for autocomplete to work, the element needs to exist, otherwise JS errors occur
    // we check for that using length
    if (jQuery("input[name=location_name]").length) {
          jQuery("input[name=location_name]").autocomplete({
            source: function(request, response) {
                         jQuery.get(self.location.href,
                                   { q: request.term,
                                          eme_admin_action: 'autocomplete_locations'
                                   },
                                   function(data){
                                                response(jQuery.map(data, function(item) {
                                                      return {
                                                         label: item.name,
                                                         name: htmlDecode(item.name),
                                                         address1: item.address1,
                                                         address2: item.address2,
                                                         city: item.city,
                                                         state: item.state,
                                                         zip: item.zip,
                                                         country: item.country,
                                                         latitude: item.latitude,
                                                         longitude: item.longitude,
                                                      };
                                                }));
                                    }, "json");
                    },
            select:function(evt, ui) {
                         // when a location is selected, populate related fields in this form
                         jQuery('input[name=location_name]').val(ui.item.name);
                         jQuery('input#location_address1').val(ui.item.address1);
                         jQuery('input#location_address2').val(ui.item.address2);
                         jQuery('input#location_city').val(ui.item.city);
                         jQuery('input#location_state').val(ui.item.state);
                         jQuery('input#location_zip').val(ui.item.zip);
                         jQuery('input#location_country').val(ui.item.country);
                         jQuery('input#location_latitude').val(ui.item.latitude);
                         jQuery('input#location_longitude').val(ui.item.longitude);
                         if(gmap_enabled) {
                            loadMapLatLong(ui.item.name, ui.item.address1, ui.item.address2, ui.item.city,ui.item.state,ui.item.zip,ui.item.country, ui.item.latitude, ui.item.longitude);
                         }
                         return false;
                   },
            minLength: 1
          }).data( "ui-autocomplete" )._renderItem = function( ul, item ) {
            return jQuery( "<li></li>" )
            .append("<a><strong>"+htmlDecode(item.name)+'</strong><br /><small>'+htmlDecode(item.address1)+' - '+htmlDecode(item.city)+ '</small></a>')
            .appendTo( ul );
          };
    } else if (jQuery("input[name='location-select-name']").length) {
          jQuery('#location-select-id').change(function() {
            jQuery.getJSON(self.location.href,{eme_admin_action: 'autocomplete_locations',id: jQuery(this).val()}, function(item){
               jQuery("input[name='location-select-name']").val(item.name);
               jQuery("input[name='location-select-address1']").val(item.address1);
               jQuery("input[name='location-select-address2']").val(item.address2);
               jQuery("input[name='location-select-city']").val(item.city);
               jQuery("input[name='location-select-state']").val(item.state);
               jQuery("input[name='location-select-zip']").val(item.zip);
               jQuery("input[name='location-select-country']").val(item.country);
               jQuery("input[name='location-select-latitude']").val(item.latitude);
               jQuery("input[name='location-select-longitude']").val(item.longitude);
               if(gmap_enabled) {
                  loadMapLatLong(item.name,item.address1,item.address2,item.city,item.state,item.zip,item.country,item.latitude,item.longitude);
               }
            })
          });
    }
}

jQuery(document).ready( function() {
   jQuery("#div_recurrence_date").hide();

   jQuery.datepick.setDefaults( jQuery.datepick.regionalOptions[eme.translate_locale_code] );
   jQuery.datepick.setDefaults({
      changeMonth: true,
      changeYear: true,
      altFormat: "yyyy-mm-dd",
      firstDay: parseInt(eme.translate_firstDayOfWeek)
   });
   jQuery("#eme_localized_start_date").datepick({ altField: "#search_start_date" });

    jQuery("#localized-start-date").datepick({
        altField: "#start-date-to-submit",
        onClose: function(dates) {
           var selected = '';
           for (var i = 0; i < dates.length; i++) {
              startDate = jQuery.datepick.formatDate(dates[i]);
              startDate_formatted = jQuery.datepick.formatDate('yyyymmdd',dates[i]);
              endDate_basic = jQuery("#localized-end-date").datepick('getDate');
              endDate_formatted = jQuery.datepick.formatDate('yyyymmdd',endDate_basic[0]);
              //jQuery("#localized-end-date").datepick( "option", "minDate", startDate);
              if (endDate_formatted<startDate_formatted) {
                jQuery("#localized-end-date").datepick( 'setDate', startDate);
              }
           }
        }
    });

    jQuery("#localized-end-date").datepick({
        altField: "#end-date-to-submit",
        onClose: function(dates) {
           var selected = '';
           for (var i = 0; i < dates.length; i++) {
              endDate = jQuery.datepick.formatDate(dates[i]);
              endDate_formatted = jQuery.datepick.formatDate('yyyymmdd',dates[i]);
              startDate_basic = jQuery("#localized-start-date").datepick('getDate');
              startDate_formatted = jQuery.datepick.formatDate('yyyymmdd',startDate_basic[0]);
              //jQuery("#localized-start-date").datepick( "option", "maxDate", endDate);
              if (startDate_formatted>endDate_formatted) {
                jQuery("#localized-start-date").datepick( 'setDate', endDate);
              }
           }
        }
     });

    jQuery("#localized-rec-start-date").datepick({
        altField: "#rec-start-date-to-submit",
        onClose: function(dates) {
           var selected = '';
           for (var i = 0; i < dates.length; i++) {
              startDate = jQuery.datepick.formatDate(dates[i]);
              startDate_formatted = jQuery.datepick.formatDate('yyyymmdd',dates[i]);
              endDate_basic = jQuery("#localized-rec-end-date").datepick('getDate');
              endDate_formatted = jQuery.datepick.formatDate('yyyymmdd',endDate_basic[0]);
              //jQuery("#localized-end-date").datepick( "option", "minDate", startDate);
              if (endDate_formatted<startDate_formatted) {
                jQuery("#localized-rec-end-date").datepick( 'setDate', startDate);
              }
           }
        }
    });

    jQuery("#localized-rec-end-date").datepick({
        altField: "#rec-end-date-to-submit",
        onClose: function(dates) {
           var selected = '';
           for (var i = 0; i < dates.length; i++) {
              endDate = jQuery.datepick.formatDate(dates[i]);
              endDate_formatted = jQuery.datepick.formatDate('yyyymmdd',dates[i]);
              startDate_basic = jQuery("#localized-rec-start-date").datepick('getDate');
              startDate_formatted = jQuery.datepick.formatDate('yyyymmdd',startDate_basic[0]);
              //jQuery("#localized-start-date").datepick( "option", "maxDate", endDate);
              if (startDate_formatted>endDate_formatted) {
                jQuery("#localized-rec-start-date").datepick( 'setDate', endDate);
              }
           }
        }
     });

   jQuery("#start-time").timeEntry({spinnerImage: '', show24Hours: show24Hours });
   jQuery("#end-time").timeEntry({spinnerImage: '', show24Hours: show24Hours });

   // if any of event_single_event_format,event_page_title_format,event_contactperson_email_body,event_respondent_email_body,event_registration_pending_email_body, event_registration_form_format, event_registration_updated_email_body
   // is empty: display default value on focus, and if the value hasn't changed from the default: empty it on blur

   jQuery('textarea#event_page_title_format').focus(function(){
      var tmp_value=eme_event_page_title_format();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   }); 
   jQuery('textarea#event_page_title_format').blur(function(){
      var tmp_value=eme_event_page_title_format();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   }); 
   jQuery('textarea#event_single_event_format').focus(function(){
      var tmp_value=eme_single_event_format();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   }); 
   jQuery('textarea#event_single_event_format').blur(function(){
      var tmp_value=eme_single_event_format();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   }); 
   jQuery('textarea#event_contactperson_email_body').focus(function(){
      var tmp_value=eme_contactperson_email_body();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   });
   jQuery('textarea#event_contactperson_email_body').blur(function(){
      var tmp_value=eme_contactperson_email_body();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   }); 
   jQuery('textarea#event_respondent_email_body').focus(function(){
      var tmp_value=eme_respondent_email_body();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   }); 
   jQuery('textarea#event_respondent_email_body').blur(function(){
      var tmp_value=eme_respondent_email_body();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   }); 
   jQuery('textarea#event_registration_recorded_ok_html').focus(function(){
      var tmp_value=eme_registration_recorded_ok_html();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   });
   jQuery('textarea#event_registration_recorded_ok_html').blur(function(){
      var tmp_value=eme_registration_recorded_ok_html();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   });
   jQuery('textarea#event_registration_pending_email_body').focus(function(){
      var tmp_value=eme_registration_pending_email_body();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   });
   jQuery('textarea#event_registration_pending_email_body').blur(function(){
      var tmp_value=eme_registration_pending_email_body();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   });
   jQuery('textarea#event_registration_updated_email_body').focus(function(){
      var tmp_value=eme_registration_updated_email_body();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   });
   jQuery('textarea#event_registration_updated_email_body').blur(function(){
      var tmp_value=eme_registration_updated_email_body();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   });
   jQuery('textarea#event_registration_cancelled_email_body').focus(function(){
      var tmp_value=eme_registration_cancelled_email_body();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   });
   jQuery('textarea#event_registration_cancelled_email_body').blur(function(){
      var tmp_value=eme_registration_cancelled_email_body();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   });
   jQuery('textarea#event_registration_denied_email_body').focus(function(){
      var tmp_value=eme_registration_denied_email_body();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   });
   jQuery('textarea#event_registration_denied_email_body').blur(function(){
      var tmp_value=eme_registration_denied_email_body();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   });
   jQuery('textarea#event_registration_form_format').focus(function(){
      var tmp_value=eme_registration_form_format();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   }); 
   jQuery('textarea#event_registration_form_format').blur(function(){
      var tmp_value=eme_registration_form_format();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   }); 
   jQuery('textarea#event_cancel_form_format').focus(function(){
      var tmp_value=eme_cancel_form_format();
      if (jQuery(this).val() == '') {
         jQuery(this).val(tmp_value);
      }
   }); 
   jQuery('textarea#event_cancel_form_format').blur(function(){
      var tmp_value=eme_cancel_form_format();
      if (jQuery(this).val() == tmp_value) {
         jQuery(this).val('');
      }
   }); 

   // initially the div is not shown using display:none, so jquery has time to render it and then we call show()
   jQuery("#event-tabs").tabs();
   jQuery("#event-tabs").show();
   jQuery( "#event-tabs" ).on( "tabsactivate", function( event, ui ) {
      // we call both functions to show the map, only 1 will work (either the select-based or the other) depending on the form shown
      if (ui.newPanel.attr('id') == "tab-locationdetails") {
         // We need to call it here, because otherwise the map initially doesn't render correctly due to hidden tab div etc ...
         eme_SelectdisplayAddress();
         eme_displayAddress(0);
         // also initialize the code for auto-complete of location info
         eme_event_location_autocomplete();
      }
   });

   updateIntervalDescriptor(); 
   updateIntervalSelectors();
   updateShowHideRecurrence();
   updateShowHideRsvp();
   updateShowHideRecurrenceSpecificDays();
   updateShowHideTime();
   jQuery('input#event-recurrence').change(updateShowHideRecurrence);
   jQuery('input#rsvp-checkbox').change(updateShowHideRsvp);
   jQuery('input#eme_prop_all_day').change(updateShowHideTime);
   // recurrency elements
   jQuery('input#recurrence-interval').keyup(updateIntervalDescriptor);
   jQuery('select#recurrence-frequency').change(updateIntervalDescriptor);
   jQuery('select#recurrence-frequency').change(updateIntervalSelectors);
   jQuery('select#recurrence-frequency').change(updateShowHideRecurrenceSpecificDays);

   // users cannot submit the event form unless some fields are filled
   function validateEventForm() {
      var errors = "";
      var recurring = jQuery("input[name=repeated_event]:checked").val();
      //requiredFields= new Array('event_name', 'localized_event_start_date', 'location_name','location_address','location_town');
      var requiredFields = ['event_name', 'localized_event_start_date'];
      var localizedRequiredFields = {'event_name':eme.translate_name,
                      'localized_event_start_date':eme.translate_date
                     };
      
      var missingFields = [];
      var i;
      for (i in requiredFields) {
         if (jQuery("input[name=" + requiredFields[i]+ "]").val() == 0) {
            missingFields.push(localizedRequiredFields[requiredFields[i]]);
            jQuery("input[name=" + requiredFields[i]+ "]").css('border','2px solid red');
         } else {
            jQuery("input[name=" + requiredFields[i]+ "]").css('border','1px solid #DFDFDF');
         }
      }
   
      if (missingFields.length > 0) {
         errors = eme.translate_fields_missing + missingFields.join(", ") + ".\n";
      }
      if (recurring && jQuery("input#localized-rec-end-date").val() == "" && jQuery("select#recurrence-frequency").val() != "specific") {
         errors = errors + eme.translate_enddate_required; 
         jQuery("input#localized-rec-end-date").css('border','2px solid red');
      } else if (recurring && jQuery("input#localized-rec-start-date").val() == jQuery("input#localized-rec-end-date").val()) {
         errors = errors + eme.translate_startenddate_identical; 
         jQuery("input#localized-rec-end-date").css('border','2px solid red');
      } else {
         jQuery("input#localized-rec-end-date").css('border','1px solid #DFDFDF');
      }
      if (errors != "") {
         alert(errors);
         return false;
      }
      return true;
   }
   jQuery('#eventForm').bind("submit", validateEventForm);

   //Prepare jtable plugin
   jQuery('#EventsTableContainer').jtable({
            title: eme.translate_events,
            paging: true,
            pageSizes: [10, 25, 50, 100],
            sorting: true,
            toolbarsearch: true,
            jqueryuiTheme: true,
            defaultSorting: 'name ASC',
            selecting: true, //Enable selecting
            multiselect: true, //Allow multiple selecting
            selectingCheckboxes: true, //Show checkboxes on first column
            selectOnRowClick: true, //Enable this to only select using checkboxes
            toolbar: {
                items: [{
                        text: eme.translate_csv,
                        click: function () {
                                  jtable_csv('#EventsTableContainer');
                               }
                        },
                        {
                        text: eme.translate_print,
                        click: function () {
                                  jQuery('#EventsTableContainer').printElement();
                               }
                        }
                        ]
            },
            actions: {
                listAction: ajaxurl+'?action=eme_events_list',
                deleteAction: ajaxurl+'?action=eme_manage_events&do_action=deleteEvents&eme_admin_nonce='+eme.translate_nonce,
            },
            fields: {
                event_id: {
                    key: true,
		    title: eme.translate_id,
                    visibility: 'hidden'
                },
                event_name: {
		    title: eme.translate_name,
                    visibility: 'fixed',
                },
                event_status: {
		    title: eme.translate_status,
                    width: '5%'
                },
                copy: {
		    title: eme.translate_copy,
                    sorting: false,
                    width: '2%',
                    listClass: 'eme-jtable-center'
                },
                rsvp: {
		    title: eme.translate_rsvp,
                    sorting: false,
                    width: '2%',
                    listClass: 'eme-jtable-center'
                },
                location_name: {
		    title: eme.translate_location
                },
                datetime: {
		    title: eme.translate_datetime,
                    width: '5%'
                },
                recinfo: {
		    title: eme.translate_recinfo,
                    sorting: false
                }
            }
        });

        // Load list from server, but only if the container is there
        if (jQuery('#EventsTableContainer').length) {
           jQuery('#EventsTableContainer').jtable('load', {
               scope: jQuery('#scope').val(),
               status: jQuery('#status').val(),
               category: jQuery('#category').val(),
               search_name: jQuery('#search_name').val(),
               search_start_date: jQuery('#search_start_date').val()
           });
        }
 
        // Actions button
        jQuery('#EventsActionsButton').button().click(function () {
           var selectedRows = jQuery('#EventsTableContainer').jtable('selectedRows');
           var do_action = jQuery('#eme_admin_action').val();
           var action_ok=1;
           if (selectedRows.length > 0) {
              if ((do_action=='deleteEvents' || do_action=='deleteRecurrence') && !confirm(eme.translate_areyousuretodeleteselected)) {
                 action_ok=0;
              }
              if (action_ok==1) {
	         var ids = [];
	         selectedRows.each(function () {
	           ids.push(jQuery(this).data('record').event_id);
	         });
    
	         var idsjoined = ids.join(); //will be such a string '2,5,7'
                 jQuery.post(ajaxurl, {"event_id": idsjoined, "action": "eme_manage_events", "do_action": do_action, "eme_admin_nonce": eme.translate_nonce }, function() {
	            jQuery('#EventsTableContainer').jtable('reload');
                 });
	      }
	   } else {
              alert(eme.translate_pleaseselectrecords);
           }
           // return false to make sure the real form doesn't submit
           return false;
        });

        // Re-load records when user click 'load records' button.
        jQuery('#EventsLoadRecordsButton').click(function (e) {
           e.preventDefault();
           jQuery('#EventsTableContainer').jtable('load', {
               scope: jQuery('#scope').val(),
               status: jQuery('#status').val(),
               category: jQuery('#category').val(),
               search_name: jQuery('#search_name').val(),
               search_start_date: jQuery('#search_start_date').val()
           });
           // return false to make sure the real form doesn't submit
           return false;
        });

	jQuery(".showhidebutton").click(function () {
        var elname= jQuery(this).data( "showhide" );
          jQuery("#"+elname).toggle();
        });


});
