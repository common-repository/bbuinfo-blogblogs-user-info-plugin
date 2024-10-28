=== BlogBlogs UserInfo Plugin ===
Contributors: rdohms
Donate link: http://www.rafaeldohms.com.br/donate
Tags: comments, blogblogs
Requires at least: 2.0.2
Tested up to: 2.3
Stable tag: 0.84

This plugin grabs user information from the BlogBlogs Index, and displays information box for registered BlogBlogs.com.br users.

== Description ==

BlogBlogs is the biggest brazilian index for blogs. This plugin is used to grab information about your site's visitors.

For every comment on your site the plugin checks if the user is registered at BlogBlogs and grabs all his/her info. This data is displayed in an information box inside the comment, or nothing is displayed if no data is found.

The script has two modes, PHP and AJAX. In PHP mode it grabs data before displaying the site content and in AJAX mode all is done in parallel. The script also implements cache, avoiding excessive queries to the BlogBlogs site.

Further information: http://www.rafaeldohms.com.br/2007/07/03/novo-plugin-blogblog-user-info/pt/
Bug Reports: http://www.rafaeldohms.com.br/dmsdev/index.php?go=projView&prj=69

== Installation ==

1. Upload all files to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the Options and set you BB API Key (http://www.rafaeldohms.com.br/dmsdev/index.php?go=taskViewAsDocu&tsk=268) and choose mode of operation.



