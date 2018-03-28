=== Events Made Easy ===  
Contributors: liedekef
Donate link: http://www.e-dynamics.be/wordpress
Tags: events, locations, booking, calendar, maps, paypal, rsvp  
Requires at least: 4.0
Tested up to: 4.8
Stable tag: 1.8.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage and display events, recurring events, locations and maps, widgets, RSVP, ICAL and RSS feeds, payment gateways support. SEO compatible.
             
== Description ==
Events Made Easy is a full-featured event management solution for Wordpress. Events Made Easy supports public, private, draft and recurring events, locations management, RSVP (+ optional approval), Paypal, 2Checkout, FirstData and Google maps. With Events Made Easy you can plan and publish your event, or let people reserve spaces for your weekly meetings. You can add events list, calendars and description to your blog using multiple sidebar widgets or shortcodes; if you are a web designer you can simply employ the template tags provided by Events Made Easy. 

Events Made Easy integrates with Google Maps; thanks to geocoding, Events Made Easy can find the location of your event and accordingly display a map. 
Events Made Easy handles RSVP and bookings, integrates payments for events using paypal and other payment gateways and allows payment tracking.

Events Made Easy provides also a RSS and ICAL feed, to keep your subscribers updated about the events you're organising. 

Events Made Easy is fully multi-site compatible.

Events Made Easy is fully customisable; you can customise the amount of data displayed and their format in events lists, locations, attendees and in the RSS/ICAL feed. Also the RSVP form can be changed to your liking with extra fields, and by using EME templates let you change the layout even per page!

Events Made Easy is fully localisable and already partially localised in Italian, Spanish, German, Swedish, French and Dutch. 

Events Made Easy is also fully compatible with qtranslate (and mqtranslate): most of the settings allow for language tags so you can show your events in different languages to different people. The booking mails also take the choosen language into account.

For more information, documentation and support forum visit the [Official site](http://www.e-dynamics.be/wordpress/) .

== Installation ==

Always take a backup of your db before doing the upgrade, just in case ...  
1. Upload the `events-made-easy` folder to the `/wp-content/plugins/` directory  
2. Activate the plugin through the 'Plugins' menu in WordPress  
3. Add events list or calendars following the instructions in the Usage section.  

= Usage =

After the installation, Events Made Easy add a top level "Events" menu to your Wordpress Administration.

*  The *Events* page lets you edit or delete the events. The *Add new* page lets you insert a new event.  
	In the event edit page you can specify the number of spaces available for your event. You just need to turn on RSVP for the event and specify the spaces available in the right sidebar box.  
	When a visitor responds to your events, the box sill show you his reservation. You can remove reservation by clicking on the *x* button or view the respondents data in a printable page.
	You can also specify the category the event is in, if you activated the Categories support in the Settings page.  
	Also fine grained control of the RSVP mails and the event layout are possible here, if the defaults you configured in the Settings page are not ok for this specific event.  
*  The *Locations* page lets you add, delete and edit locations directly. Locations are automatically added with events if not present, but this interface lets you customise your locations data and add a picture. 
*  The *Categories* page lets you add, delete and edit categories (if Categories are activated in the Settings page). 
*  The *People* page serves as a gathering point for the information about the people who reserved a space in your events. 
*  The *Pending approvals* page is used to manage registrations/bookings for events that require approval 
*  The *Change registration* page is used to change bookings for events 
*  The *Settings* page allows a fine-grained control over the plugin. Here you can set the [format](#formatting-events) of events in the Events page.
*  Access control is in place for managing events and such: 
        - a user with role "Editor" can do anything 
        - with role "Author" you can only add events or edit existing events for which you are the author or the contact person 
        - with role "Contributor" you can only add events *in draft* or edit existing events for which you are the author or the contact person 

Events list and calendars can be added to your blogs through widgets, shortcodes and template tags. See the full documentation at the [Events Made Easy Support Page](http://www.e-dynamics.be/wordpress/).
 
== Frequently Asked Questions ==

See the FAQ section at [the documentation site](http://www.e-dynamics.be/wordpress).

== Changelog ==

= 1.8.9 (2017/06/15) =
* Make sure mails are send out also when canceling a registration from the frontend if the event doesn't require approval
* Make sure mails are send out also when updating/denying a registration from the backend if the event doesn't require approval
* Make google maps work again with 'javascript in the header' setting

= 1.8.8 (2017/06/13) =
* Attempt to load google api async/defer and still get the map to work reliable on all browsers

= 1.8.7 (2017/06/12) =
* Fix how the NumberFormatter class is checked for (resulted in a php warning)
* Only load google maps in the EME admin pages where needed (was still not always the case)
* Remove a javascript comment setting, was unneeded and might cause troubles with caching/optimizing plugins/themes

= 1.8.6 (2017/06/11) =
* Fix a function call so it also works with older (unsupported) php versions
* Please read the changes concerning 1.8.5 too

= 1.8.5 (2017/06/11) =
* Show warning sign for locations with empty coordinates
* Render initial map for edit-events correctly again
* The placeholders #_EVENTPRICE, #_TOTALPRICE, #_CHARGE, #_TOTALDISCOUNT, #_BOOKINGPRICEPERSEAT, #_DYNAMICPRICE (which were already showing the localised price layout) now also show the currency symbol if the NumberFormatter php class is present (which is the case in all newer php versions).
  An option is created to deactivate this behavior if you don't like it.
  *** It might be needed to change your templates accordingly to not show multiple times the same currency symbol ***
* Add #_CURRENCYSYMBOL as placeholder to show the symbol if you need it specifically. This option only works if the php class NumberFormatter is present, which is the case in all newer php versions.
* If possible, show the currency symbol in booking table, csv and printable outputs and for mails (when using price-related placeholders). Again, see above: this option can be deactivated.
* The placeholder #_RECURRENCE_DESC now returns a list of comma-separated days in the frontend

= 1.8.4 (2017/05/27) =
* small bugfix release to fix renamed function in eme_locations.php

= 1.8.3 (2017/05/27) =
* Allow automatic reminders to be sent (in the cleanup section)
* More control over sending mails when changing registrations
* Allow resending pending or approved mail for selected bookings in the rsvp admin screens
* Revamp the edit-event screen
* Removed the fb-sync here, there's a separate plugin that does that better

= 1.8.2 (2017/05/18) =
* Fix a settings error on the map settings page

= 1.8.1 (2017/05/14) =
* Added placeholders #_AVAILABLEWAITINGLISTSEATS and #_WAITING_LIST_CLOSED, and make #_WAITING_LIST_ACTIVE actually work too
* Correct template sorting selection when sending mails
* Add a setting to ignore certificate problems for secure SMTP (some hosting providers need this ...)
* Remove a redundant end-div tag in the cancel form if the event has already passed

= 1.8.0 (2017/04/23) =
* Fix for eme_events month scope ranges
* Allow divs for calendar layout, making the calendar responsive in case you want it too (see the shortcode doc)
* Stripe payments were twice multiplied by 100, this is fixed now, together with other stripe fixes
* Make sure the example locations are created correctly for new installations
* Improvement: the 'No events' message now also has a span and not a div surrounding it, with id 'events-no-events'. Reason: div could interfere with the surrounding layout (if inside a p-tag for example)
* Waiting list feature implemented, together with some extra placeholders for events (#_WAITING_LIST_ACTIVATED and #_WAITINGLISTSEATS) and for the RSVP mails (#_ON_WAITINGLIST), see the doc
* Now one can indicate that RSVP formfields can influence the total price, this allows you to create for example "donation fields"
  or allow people to pay for extra attributes ordered (t-shirt, drinking tickets, ...)

Older changes can be found in changelog.txt
