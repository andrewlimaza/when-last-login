=== When Last Login ===
Contributors: andrewza, yoohooplugins, travislima
Tags: last login, user login, user login time, last logged in, last seen, user last seen, wordpress last login plugin, last login plugin, last seen plugin, when last login, when last user login, when last user seen
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4GC4JEZH7KSKL
Requires at least: 4.0
Tested up to: 4.7.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Show a users last login by creating a sort-able column in your users list.

== Description ==
A lightweight plugin that allows you to see active users according to their last login time. No need to configure, simply activate When Last and you're ready to go! Adds a custom column to your Users list of "Last Login" and a timestamp linked to that user. When Last also integrates with other plugins.

= Features =
* Show when last a user has logged into your site
* Sorts users according to last login time stamp (Ascending/Descending)
* Lightweight, no settings page. Activate your plugin and you're done!
* Administrator widget for top users according to login statistics
* Integration with Paid Memberships Pro - Add's a 'Last Logged In' column to the 'Members List'
* See a more detailed log of user's logins with times under 'Latest Login Records'
* Filter 'when_last_login_show_records_table'. Accepts boolean (Show/Hide Latest Login records).
* Filter 'when_last_login_show_admin_widget'. Accepts boolean (Show/Hide admin widget for top logins).
* Ability to record the user's IP address when logging in (Optional Setting)

= Premium Add-ons =
* [When Last Login - Slack Notifications](https://yoohooplugins.com/plugins/when-last-login-slack-notifications/?utm_source=plugin&utm_medium=wordpress&utm_campaign=slack_notifications)
* [When Last Login - User Statistics](https://yoohooplugins.com/plugins/when-last-login-user-statistics/?utm_source=plugin&utm_medium=wordpress&utm_campaign=user_statistics)

= When Last Login in your Language =
We are still currently developing When Last to support multiple languages and need your help. Please feel free to translate When Last Login into your language, it will help us greatly.

= Languages Supported =
* French
* Italian
* German
* Spanish

= Track Your Members Better =
You are able to track which members login to your site by simply sorting your default user's list according to when last the user was seen in easily readable text such as "X Min/Hours/Days/Weeks/Months/Years".

Newly added - administrator dashboard widget for top users according to their login count. 

= Plugins that When Last Login integrates with =
Here is a list of plugins we currently support:

* Paid Memberships Pro

= Upcoming Features =
Please note that these features are not guaranteed to be released and may change at our discretion.

* Automatic customizable emails to users that haven't logged into your site after X days/months - TBA
* Keep track of login count per user - COMPLETED
* Statistics of top logged in users - COMPLETED
* Integration with other plugins - Works with Paid Memberships Pro, more plugins TBA
* When Last Filters - UPDATED
* Show last login details for specific WordPress roles - TBA

== Installation ==
1. Upload the plugin files to the '/wp-content/plugins' directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to 'All Users' and data will be showing under 'Last Seen'
4. Users that have not yet logged in with 'When Last' active will show Never.

== Frequently Asked Questions ==

= What does When Last Login do exactly? =
When Last Login allows you to see when last users have logged into your WordPress website. This is great for big sites that require user management. 

= Is this plugin free? =
Yes, When Last Login is a free plugin for WordPress. We are looking into possibilities of creating a Pro version with a lot more features around the user data of WordPress users. We rely heavily on donations to keep all of our plugins free. If you wish to donate, please click on the donation link on the WordPress repository.

= I have activated my plugin and there is no "When Last" settings page? =
We decided that there is no need for a settings page as this plugin is lightweight and does not need configuration. 

= I have installed When Last but users are showing "Never" under "Last Login"? =
This is because users have not logged in since you have activated "When Last Login". By default we set the "Last Login" to "Never" but this can be easily changed. Once a user logs into your site, their profile will be updated from "Never" to a timestamp.

= I have updated and lost my 'Login Records' link in the WordPress dashboard =
As of version 0.6 the 'Login Records' has been moved under the 'Users' link in the admin dashboard. 

= How can I hide the 'All Login Records'? =
Add the following snippet of code to your theme's functions.php or custom plugin -

add_filter( 'when_last_login_show_records_table', '__return_false' );

= How can I hide the 'Top 3 Users' widget? =
Add the following snippet of code to your theme's functions.php or custom plugin -

add_filter( 'when_last_login_show_admin_widget', '__return_false' );


== Screenshots ==
1. When Last Login - User's list custom last login field with sorting according to "Last Login" time.
2. When Last Login - Show top 3 user login (includes 'administrators' in free version)


== Changelog ==

= 0.7 =
* New Feature: Settings page introduced
* New Feature: Ability to record a user's IP address when logging in
* Enhancement: Login Records moved under the 'When Last Login' menu item
* New Hook Added: 'wll_settings_admin_menu_item'
* New Hook Added: 'wll_logged_in_action'

= 0.6 =
* Enhancement: Moved 'All Login Records' underneath 'Users' link in dashboard.
* Filter: 'when_last_login_show_records_table'. Accepts boolean (default: true).
* Filter: 'when_last_login_show_admin_widget'. Accepts boolean (default: true).

Please have a look over at https://whenlastlogin.com#updates for more information.

= 0.5 =
* Enhancement: Ability to see which users have logged in and at what times ( Custom Post Type ) - @jarrydlong 
* Bug Fix: return default value for column data if no data is found - @seagyn
* Enhancement: Improved code readability

= 0.4 =
* Enhancement: Dashboard widget added to display top users with user count.

= 0.3 =
* Enhancement: Implemented multi language support and a couple of language files.
* Language Support: French, Spanish, German and Italian

= 0.2 =
* Bug Fixes: fixed missing 'static' on function 'sort_by_login_date'
* Error Handling: Check if 'Paid Memberships Pro' is installed, if not return from the function

= 0.1 =
* First Release

== Upgrade Notice ==
= 0.6 =
* Please update When Last Login to receive new features. Love When Last Login? Buy the developer a cup of coffee! $5.

= 0.5 =
* Please update When Last Login to receive our latest feature. Please report any bugs you find on the support forum!

= 0.4 =
* Please update When Last Login to receive our latest feature. Please report any bugs you find on the support forum!

= 0.3 =
* Please update When Last Login for language support - please see readme.txt for languages supported

= 0.2 =
* Please update When Last Login

= 0.1 =
* First Release
