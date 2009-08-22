<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * CSScaffold
 *
 * Handles all of the inner workings of the framework and juicy goodness.
 * This is where the metaphorical cogs of the system reside. 
 *
 * @package default
 * @author Anthony Short
 **/
final class CSScaffold 
{	 
	/**
	 * Holds the array of plugin objects
	 *
	 * @var array
	 **/ 
	 public static $plugins;
	 
	/**
	 * Holds the array of module objects
	 *
	 * @var array
	 **/ 
	 public static $modules;
	 
	 /**
	 * What plugins have been loaded (Just their name)
	 *
	 * @var array
	 **/ 
	 public static $loaded = array();
	 
	/**
	 * Sets the initial variables, checks if we need to process the css
	 * and then sends whichever file to the browser.
	 *
	 * @return void
	 * @author Anthony Short
	 **/
	public static function setup($url_params) 
	{
		# Define Scaffold error constant
		define('E_SCAFFOLD', 42);
		
		# Set error handler
		set_error_handler(array('CSScaffold', 'exception_handler'));
		
		# Set exception handler
		set_exception_handler(array('CSScaffold', 'exception_handler'));

		# Get rid of those pesky slashes
		$requested_file	= trim_slashes($url_params['request']);
		
		# If they've put a param in the url, consider it set to 'true'
		foreach($url_params as $key => $value)
		{
			if($value == "")
			{
				$url_params[$key] = true;
			}
		}

		$request = pathinfo($requested_file);
		
		# Add our requested file var to the array
		$request['requested_file'] = $requested_file;
		
		# Full server path to the requested file
		$request['server_path'] = join_path(DOCROOT,$requested_file);
		
		# Path to the file, relative to the css directory		
		$request['relative_file'] = substr($requested_file, strlen(CSSURL));
		
		# Path to the directory containing the file, relative to the css directory		
		$request['relative_dir'] = pathinfo($request['relative_file'], PATHINFO_DIRNAME);	

		# If the file doesn't exist
		if(!file_exists($request['server_path']))
		{
			throw new Scaffold_exception("Can't seem to find your css file - " . $request['server_path'] . ". Check your paths in the config"); 
		}

		# or if it's not a css file
		elseif (!is_css($requested_file))
		{
			stop("Error: Request file isn't a css file");
		}
		
		# or if the requested file wasn't from the css directory
		elseif(!substr(pathinfo($request['server_path'], PATHINFO_DIRNAME), 0, strlen(CSSPATH)))
		{
			stop('Error: The file wasn\'t requested from the css directory. Check your css path in your config, or the path to the css file you just requested');
		}
		
		elseif(isset($url_params['raw']))
		{
			self::output_raw($request['server_path']);
		}
		
		# Make sure the files/folders are writeable
		if (!is_dir(CACHEPATH) || !is_writable(CACHEPATH))
		{
			stop("Cache path (".CACHEPATH.") is not writable or does not exist");
		}
		
		# Send it off to the config
		Config::set($request);
		Config::set($url_params);
					
		# Get the modified time of the CSS file
		Config::set('requested_mod_time', filemtime(Config::get('server_path')));
		
		# Load the plugins and flags
		self::$modules = self::load_addons(read_dir(SYSPATH . "/modules"));
		self::$plugins = self::load_addons(read_dir(SYSPATH . "/plugins"));
					
		if(Config::get('always_recache'))
		{
			$recache = true;
		}
		elseif(isset($url_params['recache']))
		{
			$recache = true;
		}
		else
		{
			$recache = false;
		}
		
		if(Config::get('cache_lock') === true)
		{
			$recache = false;
		}
	
		# Prepare the cache, and tell it if we want to recache
		Cache::set($recache);
	}
	
	/**
	 * Handles Exceptions
	 *
	 * @param   integer|object  exception object or error code
	 * @param   string          error message
	 * @param   string          filename
	 * @param   integer         line number
	 * @return  void
	 */
	public function exception_handler($exception, $message = NULL, $file = NULL, $line = NULL)
	{
		try
		{			
			# PHP errors have 5 args, always
			$PHP_ERROR = (func_num_args() === 5);
	
			# Test to see if errors should be displayed
			#if ($PHP_ERROR AND (error_reporting() & $exception) === 0)
			#	return;
				
			# Error handling will use exactly 5 args, every time
			if ($PHP_ERROR)
			{
				$code     = $exception;
				$type     = 'PHP Error';
			}
			else
			{
				$code     = $exception->getCode();
				$type     = get_class($exception);
				$message  = $exception->getMessage();
				$file     = $exception->getFile();
				$line     = $exception->getLine();
			}

			if(is_numeric($code))
			{
				$codes = array
				(
					E_SCAFFOLD           => array( 1, 'Scaffold Error',   'Please check the Scaffold documentation for information about the following error.'),

					E_RECOVERABLE_ERROR  => array( 1, 'Recoverable Error', 'An error was detected which prevented the loading of this page. If this problem persists, please contact the website administrator.'),
					E_ERROR              => array( 1, 'Fatal Error',       ''),
					E_USER_ERROR         => array( 1, 'Fatal Error',       ''),
					E_PARSE              => array( 1, 'Syntax Error',      ''),
					E_WARNING            => array( 1, 'Warning Message',   ''),
					E_USER_WARNING       => array( 1, 'Warning Message',   ''),
					E_STRICT             => array( 2, 'Strict Mode Error', ''),
					E_NOTICE             => array( 2, 'Runtime Message',   ''),
				);
	
				if (!empty($codes[$code]))
				{
					list($level, $error, $description) = $codes[$code];
				}
				else
				{
					$level = 1;
					$error = $PHP_ERROR ? 'Unknown Error' : get_class($exception);
					$description = '';
				}
			}
			else
			{
				// Custom error message, this will never be logged
				$level = 5;
				$error = $code;
				$description = '';
			}
			
			// Remove the DOCROOT from the path, as a security precaution
			$file = str_replace('\\', '/', realpath($file));
			$file = preg_replace('|^'.preg_quote(DOCROOT).'|', '', $file);

			if($PHP_ERROR)
			{
				$description = 'An error has occurred which has stopped Scaffold';
	
				if (!headers_sent())
				{
					# Send the 500 header
					header('HTTP/1.1 500 Internal Server Error');
				}
			}
			else
			{
				if (method_exists($exception, 'sendHeaders') AND !headers_sent())
				{
					# Send the headers if they have not already been sent
					$exception->sendHeaders();
				}
			}
			
			$message = "<ul><li>" . implode("</li><li>", $message) . "</li></ul>";
			
			require(SYSPATH . '/views/error.php');
	
			# Turn off error reporting
			error_reporting(0);
			exit;
		}
		catch(Exception $e)
		{
			die('Fatal Error: '.$e->getMessage().' File: '.$e->getFile().' Line: '.$e->getLine());
		}
	}
		
	/**
	 * Runs CSScaffold
	 *
	 * @author Anthony Short
	 * @return null
	 */
	public static function start()
	{		
		# Parse the css
		self::parse_css();
	
		# Send it to the browser
		self::output_css();
	}

	/**
	 * Shows the raw CSS file
	 *
	 * @author Anthony Short
	 * @param $css
	 * @return null
	 */
	public function output_raw($css)
	{
		self::set_headers();
		echo(file_get_contents($css));
		exit;
	}
		
	/**
	 * Sets the headers
	 *
	 * @author Anthony Short
	 * @return null
	 */
	private function set_headers($last_mod = "")
	{
		header('Content-Type: text/css');
		header("Vary: User-Agent, Accept");
		
		if($last_mod != "")
		{
			header('Last-Modified: '. gmdate('D, d M Y H:i:s', $last_mod) .' GMT');
		}
	}
		
	/**
	 * Loads the Plugins
	 *
	 * @return boolean
	 * @author Anthony Short
	 **/
	private static function load_addons($folders)
	{			
		$plugins = array();
		
		foreach($folders as $plugin_folder)
		{
			$plugin_files = read_dir($plugin_folder);
			
			foreach($plugin_files as $plugin_file)
			{
				$library_path = join_path($plugin_folder, "libraries");
				
				# Include the libraries
				if($libraries = read_dir($library_path))
				{
					foreach($libraries as $library)
					{
						require_once($library);
					}
				}
				
				if(extension($plugin_file) == "php" && pathinfo($plugin_file, PATHINFO_FILENAME) != "config")
				{					
					require_once($plugin_file);
					
					$plugin_class = pathinfo($plugin_file, PATHINFO_FILENAME);
					
					$config = join_path($plugin_folder, 'config.php');
					
					if(file_exists($config))
					{
						Config::load($config, $plugin_class);
					}
										
					if(class_exists($plugin_class))
					{				
						# Initialize the plugin
						$plugins[$plugin_class] = new $plugin_class();
						
						# Set the member paths
						$plugins[$plugin_class]->set_paths($plugin_folder);
					}
					
					# Add the plugin to the loaded array
					self::$loaded[] = $plugin_class;
				}			
			}
		}
		
		return $plugins;
	}
	
	/**
	 * Parse the CSS
	 *
	 * @return string - The processes css file as a string
	 * @author Anthony Short
	 **/
	private static function parse_css()
	{						
		# If the cache is stale or doesn't exist
		if (Config::get('cached_mod_time') < Config::get('requested_mod_time'))
		{
			# Start the timer
			Benchmark::start("system");
			
			# Load the CSS file in the object
			CSS::load(file_get_contents(Config::get('server_path')));
			
			# Import CSS files
			Import::parse();
			
			# Import the mixins in the plugin folders
			$plugin_folders = read_dir(join_path(SYSPATH, 'plugins'));
			$module_folders = read_dir(join_path(SYSPATH, 'modules'));

			Mixins::import_mixins(array_merge($plugin_folders, $module_folders));
														
			# Parse our css through the plugins
			foreach(self::$plugins as $plugin)
			{
				$plugin->import_process();
			}
			
			# Compress it before parsing
			CSS::compress(CSS::$css);

			# Parse the constants
			Constants::parse();

			foreach(self::$plugins as $plugin)
			{
				$plugin->pre_process();
			}
			
			# Replace the constants
			Constants::replace();
			
			# Parse @for loops
			For_loops::parse();
			
			# Compress it before parsing
			CSS::compress(CSS::$css);
			
			foreach(self::$plugins as $plugin)
			{
				$plugin->process();
			}
			
			# Parse the mixins
			Mixins::parse();
			
			# Find missing constants
			Constants::replace();
			
			# Compress it before parsing
			CSS::compress(CSS::$css);
			
			foreach(self::$plugins as $plugin)
			{
				$plugin->post_process();
			}
			
			# Parse the expressions
			Expression::parse();
			
			# Parse the nested selectors
			NestedSelectors::parse();
			
			# Add the extra string we've been storing
			CSS::$css .= CSS::$append;
			
			foreach(self::$plugins as $plugin)
			{
				$plugin->formatting_process();
			}
			
			# Stop the timer...
			Benchmark::stop("system");
			
			if (Config::get('show_header') === TRUE)
			{		
				CSS::$css  = "/* Processed by CSScaffold on ". gmdate('r') . " in ".Benchmark::get("system", "time")." seconds */\n\n" . CSS::$css;
			}
			
			# Write the css file to the cache
			Cache::write(CSS::$css);
		} 
	}
		
	/**
	 * Output the CSS to the browser
	 *
	 * @return void
	 * @author Anthony Short
	 **/
	private function output_css()
	{		
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'], $_SERVER['SERVER_PROTOCOL']) && Config::get('cached_mod_time') <= strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))
		{
			header("{$_SERVER['SERVER_PROTOCOL']} 304 Not Modified");
			exit;
		}
		else
		{			
			$css = file_get_contents(Cache::$cached_file);
			
			if (Config::get('debug') === true)
			{
				self::debug($css);
			}
			
			self::set_headers(Config::get('cached_mod_time'));
			echo $css;
			exit();
		}
	}

}