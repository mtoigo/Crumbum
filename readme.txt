Updated by Ahmad saad solve the following bugs && add some parameters:
1- Fix a bug: when use list_home ="no" and list_current="no" showing error "undefined variable (html)" (this happend when the crumb contain just 1 element).
2- add ability to add HTML code to crumb_char param like (<img src="http://www.example/arrow.png" />).
3- add parent_start && parent_end parameters to set the a container to the crumb link.(e.g parent_start='<div id="nav">' parent_end='</div>' generate <div id="nav"><a href="http://www.example/">Home</a>). Default value "" .
4- add custom_title parameter to call a custom field text as a crumb title. Default value "title" .
5- add entry_id && url_title parameter to show a new crumb item , when show a single entry page. Default value "" .
6- add entry_custom_title parameter to call a custom field text as a crumb title. Default value "title" .
7- add url_entry parameter to specify how curmb will generate the entry link (if link_current="yes" offcours). values(entry_id|url_title Default)

Thanks for downloading Crumbum. Full documentation is available at:

http://www.matt-toigo.com/crumbum