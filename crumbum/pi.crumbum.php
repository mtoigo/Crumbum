<?php
/* Crumbum Version 1.3
For support visit http://www.matt-toigo.com/crumbum
By downloading this software you have agreed to the terms defined at http://www.matt-toigo.com/crumbum */

$plugin_info = array(
  'pi_name' => 'Crumbum',
  'pi_version' => '1.3',
  'pi_author' => 'Matt Toigo',
  'pi_author_url' => 'http://www.matt-toigo.com/crumbum',
  'pi_description' => 'Generates breadcrumbs based on URL structure defined in the weblog used by the built in Expression Engine Pages module.',
  'pi_usage' => Crumbum::usage()
  );

class Crumbum //Must use php4 style constructor
{
	public $return_data = "";
	private $weblogID = '';
	private $siteID = '';
	
	private $pages;

  	function crumbs()
  	{
  		$this->EE =& get_instance();
	    global $TMPL, $session;
	    
	    //Get any arguments passed
	    $ourParams = array('short_name', 'list_home', 'home_url', 'crumb_char', 'list_current', 'link_current', 'debug');
	    foreach($ourParams as $param)
	    	$params[$param] = $this->EE->TMPL->fetch_param($param);
	    
	    try
	    {
	    	$debugString = 'PASSED PARAMS<pre>'.print_r($params, true);
	
	    	$params = $this->checkParams($params);
	    	$this->populatePages();
	    	$crumbHTML = $this->getCrumbs($params);
	    	
	    	$debugString .= 'DETERMINED PARAMS '.print_r($params, true);
	    	$debugString .= 'PAGES '.print_r($this->pages, true).'</pre>';
	    	$debugString .= $crumbHTML;
	    	
	    	if($params['debug'] && $this->EE->session->userdata['group_id']==1)
	    	{
	    		return $debugString;
	    	}
	    	else
	    	{
	    		return $crumbHTML;
	    	}
	    }
	    catch(Exception $e)
	    {
	    	//If they are logged in as a Super Admin, display our error, otherwise, display nothing
	    	if($this->EE->session->userdata['group_id']==1)
	    		return '<span style="color: red"><strong>Crumbum Error:</strong> '.$e->getMessage().'</span>';
	    	else
	    		return '';
	    }
  	}
  	
  	//Checks arguments and sets defaults
  	private function checkParams($params)
  	{
  		$this->EE =& get_instance();
  		
  		//Check for any SQL related characters in the params
  		$badChars = array('\'', '"', ',', ';');
  		$chars = implode('<br />', $badChars);
  		foreach($params as $param)
  		{
  			foreach($badChars as $char)
  			{
  				if(strstr($param, $char))
  					throw new Exception('Arguments contain illegal characters. The following characters are not allowed in Crumbum arguments<br />'.$chars);
  			}
  		}
  	
  		//Check to make sure the Pages module is installed
  		$checkPagesModule = "SELECT module_name
  							 FROM exp_modules
  							 WHERE module_name = 'Pages';";
  							 
  		$query = $this->EE->db->query($checkPagesModule);
  		if($query->num_rows()==0)
  			throw new Exception('The Expression Engine Pages module is not installed. Go to the Modules tab in your control panel to install it. If you don\'t see it there, you likely need the Personal or Commercial version <a href="http://expressionengine.com/overview/pricing">http://expressionengine.com/overview/pricing</a>');
  	
  		//If they specified a short_name
  		if($params['short_name']!='')
  		{
	  		//Make sure they fed a valid weblog short tag name
	  		$findWeblogID = "SELECT channel_id, site_id
	  						 FROM exp_channels
	  						 WHERE channel_name = '".$params['short_name']."';";
	  						
	  		$query = $this->EE->db->query($findWeblogID);
	  		
	  		if(@$query->num_rows()>0)
	  		{
	  			$this->weblogID = $query->row('channel_id');
	  			$this->siteID = $query->row('site_id');
	  		}
	  		else
	  		{
	  			throw new Exception('Invalid short_name. Please check Admin -> Channel Administration -> Channels to find the short name of your channel.');
	  		}
  		}
  		else
  		{	
  			$getDefaultPagesWeblog = "SELECT configuration_value
  									  FROM exp_pages_configuration
  									  WHERE configuration_name = 'default_channel';";
  			
  			$query = $this->EE->db->query($getDefaultPagesWeblog);
  			
  			$defaultPagesWeblog = $query->row('configuration_value');
  			
	  		if($defaultPagesWeblog>0)
	  		{
	  			$this->weblogID = $defaultPagesWeblog;
	  			
	  			//Find our siteID
	  			$findSiteID = "SELECT site_id
	  						 FROM exp_channels
	  						 WHERE channel_id = '".$defaultPagesWeblog."';";
	  						
	  			$query = $this->EE->db->query($findSiteID);
				$this->siteID = $query->row('site_id');	  			
	  		}
  			else
  			{
  				throw new Exception("The weblog for the Expression Engine Pages Module could not be determined. You can either set a weblog in the Control Panel for <em>Modules -> Pages -> Module Configuration</em> for <em>Default Weblog for 'Create New Page' Tab</em> or use the short_name argument to specify the weblog that the Expression Engine Pages Modules uses.");
  			}
  		}
  		
  		//List_home
  		if(@$params['list_home']!='yes' && @$params['list_home']!='no' && @$params['list_home']!='')
  			throw new Exception('Invalid argument for list_home. Valid arguments are yes or no.');
  		if(@$params['list_home']=='')
  			$params['list_home'] = 'yes';
  		
  		//List_home
  		if(@$params['home_url']=='')
  		{
  			$params['home_url'] = substr($_SERVER['SCRIPT_NAME'], 0, -10);  		
  		}
  		else
			{
				//Trim trailing slash, EE TMPL class encodes the URL
				if(substr($params['home_url'], -5)=='&#47;')
					$params['home_url'] = substr($params['home_url'], 0, -5);
			}
			
			//Should we be including index.php in bread crumb links
			if(strstr($_SERVER['REQUEST_URI'], 'index.php'))
				$params['index'] = '/index.php';
			else
				$params['index'] = '';
			
  		
  		//Crumb char
  		if(@$params['crumb_char']=='')
  			$params['crumb_char'] = ' &raquo; ';
  	
  		 //List_current
  		if(@$params['list_current']!='yes' && @$params['list_current']!='no' && @$params['list_current']!='')
  			throw new Exception('Invalid argument for list_current. Valid arguments are yes or no.');
  		if(@$params['list_current']=='')
  			$params['list_current'] = 'yes';
  			
  		//Link_current
  		if(@$params['link_current']!='yes' && @$params['link_current']!='no' && @$params['link_current']!='')
  			throw new Exception('Invalid argument for link_current. Valid arguments are yes or no.');
  		if(@$params['link_current']=='')
  			$params['link_current'] = 'no';
  	
  		return $params;
  	}
  	
  	//Get everything we need
  	private function populatePages()
	{
		$pageURLs = $this->getPageURLs();
		$this->setPageTitles($pageURLs);
		$this->sortByURL();
	}
	
	//Grabs our pages from the CMS
	private function getPageURLs()
	{
		$this->EE =& get_instance();
		global $DB;	
		/* Get an array where pages URLs are elements and their ids are the keys */
		$getPages = "SELECT site_pages FROM exp_sites WHERE site_id = {$this->siteID};";
		$query = $this->EE->db->query($getPages);
		$pages = unserialize(base64_decode($query->row('site_pages')));
		
		//Changed in EE1.6.8 to 1.6.9
		//Check for new array location, if it's not there then try the old one
		if(is_array(@$pages[1]['uris']))
			return $pages[1]['uris'];
		else
			return $pages['uris'];
	}
	
	//Grabs page titles and IDs, setPages must be called first, take a collection of page URLs from getPageURLs
	private function setPageTitles($pageURLs)
	{
		$this->EE =& get_instance();
		
		if(empty($pageURLs))
			throw new Exception('You have no entries in your weblog with URLs defined by the Expression Engine Pages Module');
	
		/* Get users and their pages */
		$getAllPages = "SELECT exp_channel_data.entry_id, title, edit_date
		FROM exp_channel_data
		INNER JOIN exp_channel_titles ON exp_channel_titles.entry_id = exp_channel_data.entry_id
		WHERE exp_channel_data.channel_id = {$this->weblogID};";
		
		$query = $this->EE->db->query($getAllPages);
		foreach($query->result_array() as $page)
		{
			$url = @$pageURLs[$page['entry_id']];
			$this->pages[] = new CrumbumPage($page['entry_id'], $page['title'], $url);
		}
	}
	
	//Used for debugging
	public function __toString()
	{
		$debug = print_r($this->pages, true);
		return $debug;
	}
	
	//Places pages in order by their URL
	private function sortByURL()
	{
		//sort($this->pages, SORT_STRING); - ONLY WORKS RIGHT IN PHP5.2 due to __toString bug
		usort($this->pages, "CrumbumObjectWeightSort"); //function is defined at bottom of file
	}
	
	private function getURLParts()
	{
		$this->EE =& get_instance();

		//Start the array at 0
		foreach($this->EE->uri->segments as $chunk)
			$chunks[] = $chunk;
		
		return @$chunks;
	}
	
	private function getCrumbs($params)
	{	
		$urlParts = $this->getURLParts();
		if(empty($this->pages))
			throw new Exception('No entries in the specified weblog have a Page URL defined.');
		
		$lookingFor = 0;
		foreach($this->pages as $page)
		{
			if(strstr($page->url, @$urlParts[$lookingFor]))
			{
				$crumbs[] = $page;
				$lookingFor++; //Could be more efficient to break when it finds everything it needs
			}
		}
		$crumbCount = count(@$crumbs);
		
		if($params['list_home']=='yes')
		{
			$html = '<a href="'.$params['home_url'].'">Home</a>' ;
			$html .= $params['crumb_char'];
		}
		
		if($params['list_current']=='yes')
			$stopAt	= $crumbCount;
		else
			$stopAt = $crumbCount-1;
			
		for($i=0;$i<$stopAt;$i++)
		{
			if(($i+1!=$stopAt && $params['list_current']=='yes') || ($i!=$stopAt && $params['list_current']=='no') || ($params['link_current']=='yes' && $params['list_current']=='yes'))
				@$html .= '<a href="'.$params['home_url'].$params['index'].$crumbs[$i]->url.'">'.$crumbs[$i]->title.'</a>';
			else
				@$html .= $crumbs[$i]->title;
				
			if($i+1!=$stopAt)
				$html .= $params['crumb_char'];
		}

		return $html;
	}

	//Documentation in the control panel
	public function usage()
	{
		$supportText = 'Please visit 
		
		http://www.matt-toigo.com/crumbum 
		
		for full documentation.';
		
		return $supportText;
	}

}

//Class to hold data regarding a page
class CrumbumPage
{
	public $id;
	public $title;
	public $url;
	
	public function __construct($id, $title, $url = '')
	{
		$this->id = $id;
		$this->title = $title;
		$this->url = $url;
	}
	
	//ONLY WORKS RIGHT IN PHP 5.2 Outputs a pages URL and converts it to string, makes sorting easier, not currently used
	public function __toString()
	{
		return "{$this->url}";
	}
}

//Get around pre PHP 5.2 __toString bug that messes up sorting
function CrumbumObjectWeightSort($lhs, $rhs)
{
   if ($lhs->url == $rhs->url)
     return 0;

   if ($lhs->url > $rhs->url)
     return 1;

   return -1;
}
?>