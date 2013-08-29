<?php
/**
 * The Getsocialdata application.
 *
 * @copyright  Robert Deutz Business solution
 * @author Robert Deutz <rdeutz@googlemail.com>
 * @license    WTFPL
 */

namespace Getsocialdata;

use Joomla\Application\AbstractCliApplication;
use Joomla\Database\DatabaseDriver;
use Joomla\Factory;
use Joomla\Log\Log;
use Joomla\Registry\Registry;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;

use Symfony\Component\DomCrawler\Crawler;

use JConfig;
use Facebook;

/**
 * The Getsocialdata application class.
 *
 * @since  1.0
 */
class Application extends AbstractCliApplication
{
	/**
	 * The application version.
	 *
	 * @var    string
	 * @since  1.0
	 */
	const VERSION = '1.0';

	/**
	 * The database driver object.
	 *
	 * @var    DatabaseDriver
	 * @since  1.0
	 */
	private $database;

	/**
	 * The Account Data
	 *
	 * @var    array
	 * @since  1.0
	 */
	private $aData;


	/**
	 * Class constructor.
	 *
	 * @since   1.0
	 */
	public function __construct()
	{
		$logOptions = array(
						'text_file' => 'log.php',
						'text_file_path' => JPATH_BASE
						);

		Log::addLogger($logOptions);

		// Add a message.
		Log::add('Start Logging:' . date('d.m.Y H:i'));

		// Run the parent constructor
		parent::__construct();

		// Load the configuration object.
		$this->loadConfiguration();

		// Register the application to Factory
		// @todo Decouple from Factory
		Factory::$application = $this;
		Factory::$config = $this->config;
	}

	/**
	 * Initialize the configuration object.
	 *
	 * @return  $this  Method allows chaining
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function loadConfiguration()
	{
		// Set the configuration file path for the application.
		$file = JPATH_CONFIGURATION . '/configuration.php';

		// Verify the configuration exists and is readable.
		if (!is_readable($file))
		{
			throw new \RuntimeException('Configuration file does not exist or is unreadable.');
		}

		// Load the configuration file into an object.
		require $file ;
		$config = new JConfig;

		if ($config === null)
		{
			throw new \RuntimeException(sprintf('Unable to parse the configuration file %s.', $file));
		}

		// Get the FB config file path
		$file = JPATH_BASE . '/config_fb.json';

		// Verify the configuration exists and is readable.
		if (!is_readable($file))
		{
			throw new \RuntimeException('FB-Configuration file does not exist or is unreadable.');
		}

		// Load the configuration file into an object.
		$configFb = json_decode(file_get_contents($file));

		if ($configFb === null)
		{
			throw new \RuntimeException(sprintf('Unable to parse the configuration file %s.', $file));
		}

		// Merge the configuration
		$config->fb_app_id 		= $configFb->app_id;
		$config->fb_app_secret 	= $configFb->app_secret;

		$this->config->loadObject($config);

		return $this;
	}

	/**
	 * Execute the application.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function doExecute()
	{
		// Check if help is needed.
		if ($this->input->get('h') || $this->input->get('help'))
		{
			$this->help();

			return;
		}

		$this->aData = $this->getAccountData();
		Log::add('Count Accounts:' . count($this->aData));

		$this->getFacebookData();
		$this->getTwitterData();
		$this->rewriteData();

		// That's all
		Log::add('End Logging:' . date('d.m.Y H:i'));
	}

	/**
	 * rewriting the data back into the data base, makes sense :-)
	 *
	 * @return  void
	 */
	protected function rewriteData()
	{
		$db = $this->getDatabase();

		$fields = array('someval_facebook_valid', 'someval_facebook_type', 'someval_facebook_friends_or_likes',
						'someval_twitter_valid', 'someval_twitter_tweets', 'someval_twitter_followers', 'someval_twitter_following');

		foreach ($this->aData AS &$elm)
		{
			$query = $db->getQuery(true);

			$query->update($db->qn('#__accountdata'))
					->where('bid = ' . $elm->bid);

			foreach ($fields as $f)
			{
				$query->set($db->qn($f) . ' = ' . $db->q($elm->$f));
			}

			$db->setQuery($query);

			if (!$db->execute())
			{
				Log::add('Something went wrong for bid=:' . $elm->bid);
			}
		}
	}

	/**
	 * Yes you got it right I am NOT using the twitter API. The rescritions of
	 * 150 request per hour makes it hard to get a large ammount of data
	 *
	 * @return  void
	 */
	protected function getTwitterData()
	{
		$http = HttpFactory::getHttp();

		$fields = array('profile' 	=> 'someval_twitter_tweets',
						'following' => 'someval_twitter_following',
						'followers' => 'someval_twitter_followers');

		foreach ($this->aData AS &$elm)
		{

			if (trim($elm->w20_twitter) == '')
			{
				continue;
			}

			$response = $http->get('http://twitter.com/'.$elm->w20_twitter);

			if ($response->code == 200)
			{
				// before we parse the DOM check if we have a 'data-nav="profile"' within the response
				$html = (string) $response->body;
				if (strpos($html, 'data-nav="profile"') === false)
				{
					$elm->someval_twitter_valid = 0;
					Log::add('Twitter Invalid (redirect):' . $elm->w20_twitter);
					continue;
				}

				$crawler = new Crawler($html);
				$ul 	 = $crawler->filter('ul.stats');
				$li 	 = $ul->children();

				foreach ($li as $key => $value)
				{
					$a 		= $value->firstChild;
					$type 	= $a->getAttribute('data-nav');
					if (in_array($type, array_keys($fields)))
					{
						$val = $a->nodeValue;
						list($t) = explode(' ', trim($val));
						$num = (int) str_replace('.','',$t);

						if ($type == 'profile')
						{
							if ($num != 0)
							{
								$elm->someval_twitter_valid = 1;
							}
						}

						if ($num != 0)
						{
							$elm->$fields[$type] = $num;
						}

					}
				}
				Log::add('Twitter Valid:' . $elm->w20_twitter
						. ' | Tweets=' . $elm->someval_twitter_tweets
						. ' | Following=' . $elm->someval_twitter_following
						. ' | Followers=' . $elm->someval_twitter_followers);
			}
			else
			{
				$elm->someval_twitter_valid = 0;
				Log::add('Twitter Invalid:' . $elm->w20_twitter);
			}
		}
	}

	/**
	 * this is NOT using the Joomla Facebook class, I am using the PHP SDK from Facebook
	 * the main reason is that I didn't get it running with the joomla class and it works
	 * more or less out of the box with the SDK from facebook.
	 *
	 * @return  [type]  [description]
	 */
	protected function getFacebookData()
	{
		$config = array();
		$config['appId'] = $this->config->get('fb_app_id');
		$config['secret'] = $this->config->get('fb_app_secret');
		$config['fileUpload'] = false; // optional

		$facebook = new Facebook($config);
		$access_token = $facebook->getAccessToken();

		foreach ($this->aData AS &$elm)
		{

			if (trim($elm->w20_facebook) == '')
			{
				continue;
			}

			$url = urlencode('https://www.facebook.com/' . $elm->w20_facebook);

			$query = "SELECT+site,id,type+FROM+object_url+WHERE+url='$url'";
			$fql_query_url = 'https://graph.facebook.com/'
			    . 'fql?q=' . $query
			    . '&access_token=' . $access_token;
			$fql_query_result = file_get_contents($fql_query_url);
			$fql_query_obj = json_decode($fql_query_result, true);

			$r = $fql_query_obj['data'];

			if (empty($r))
			{
				$elm->someval_facebook_valid = 0;
				Log::add('Facebook Invalid:' . $elm->w20_facebook);
			}
			else
			{
				$elm->someval_facebook_valid = 1;
				$type  = $r[0]['type'];
				$fb_id = $r[0]['id'];

				$elm->someval_facebook_type = $type;

				Log::add('Facebook Valid:' . $elm->w20_facebook . ' | Type=' . $type . ' | Id=' . $fb_id);

				if ($type == 'page')
				{
					$query = "SELECT+fan_count+FROM+page+WHERE+page_id='$fb_id'";
					$fql_query_url = 'https://graph.facebook.com/'
					    . 'fql?q=' . $query
					    . '&access_token=' . $access_token;
					$fql_query_result = file_get_contents($fql_query_url);
					$fql_query_obj = json_decode($fql_query_result, true);

					$r = $fql_query_obj['data'];

					$elm->someval_facebook_friends_or_likes = $r[0]['fan_count'];
					Log::add('Facebook Fancount:' . $r[0]['fan_count']);
				}

				if ($type == 'profile')
				{
					$query = "SELECT+friend_count+FROM+user+WHERE+uid=$fb_id";
					$fql_query_url = 'https://graph.facebook.com/'
					    . 'fql?q=' . $query
					    . '&access_token=' . $access_token;
					$fql_query_result = file_get_contents($fql_query_url);
					$fql_query_obj = json_decode($fql_query_result, true);

					$r = $fql_query_obj['data'];
					$elm->someval_facebook_friends_or_likes = $r[0]['friend_count'];
				}
			}
		}
	}

	/**
	 * Get a database driver object.
	 *
	 * @return  DatabaseDriver
	 *
	 * @since   1.0
	 */
	public function getDatabase()
	{
		if (is_null($this->database))
		{
			$this->database = DatabaseDriver::getInstance(
				array(
					'driver' => $this->config->get('dbtype'),
					'host' => $this->config->get('host'),
					'user' => $this->config->get('user'),
					'password' => $this->config->get('password'),
					'database' => $this->config->get('db'),
					'prefix' => $this->config->get('dbprefix')
				)
			);

			// @todo Decouple from Factory
			Factory::$database = $this->database;
		}

		return $this->database;
	}


	/**
	 * get's account data from the database, tables are hardcoded
	 * I deeply apologise but I need to get this running
	 *
	 * @return  array 	array of data objects
	 */
	protected function getAccountData()
	{
		$db = $this->getDatabase();

		$query = $db->getQuery(true);

		$query->select('*')
				->from('#__accountdata')
				->where('(w20_facebook <> "" OR w20_twitter <> "")')
				->where('typ = 0')
				// ->where('bid IN (2657)')
				->where('status = 0');


		$db->setQuery($query);
		$this->adata = $db->loadObjectList();

		return $this->adata;
	}


	/**
	 * Display the help text.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function help()
	{
		$this->out('Getsocialdata ' . self::VERSION);
		$this->out();
		$this->out('Usage:     php -f bin/getdata.php -- [switches]');
		$this->out();
		$this->out('Switches:  -h | --help    Prints this usage information.');
		$this->out();
		$this->out();
	}
}