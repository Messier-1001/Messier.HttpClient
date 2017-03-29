<?php
/**
 * @author     Messier 1001 <messier.1001+code@gmail.com>
 * @copyright  ©2017, Messier 1001
 * @package    Messier\HttpClient
 * @since      2017-03-28
 * @version    0.1.0
 */


declare( strict_types = 1 );


namespace Messier\HttpClient;


/**
 * Defines a class that …
 *
 * @since v0.1.0
 */
class Client
{


   // <editor-fold desc="// – – –   P R I V A T E   F I E L D S   – – – – – – – – – – – – – – – – – – – – – – – –">

   /**
    * @type array
    */
   private $_nextRequestGetParams = [];

   /**
    * The user agent string
    *
    * @type string
    */
   private $_userAgent = self::DEFAULT_USER_AGENT;

   /**
    * If TRUE, the client creates a temporary file for cookies.
    *
    * @type bool
    */
   private $_useRandomCookieFile = false;

   /**
    * The Prefix for random cookie file.
    *
    * @type string
    */
   private $_randCookieFilePrefix = self::DEFAULT_RAND_COOKIE_FILE_PREFIX;

   /**
    * The path of current used cookie file
    *
    * @type string
    */
   private $_cookieFile;

   private $_curlHandle;

   private $_curlInfo;

   /**
    * If here a valid file path is defined, the last response is stored inside the file.
    *
    * @type string|null
    */
   private $_lastResponseToFile = null;

   /**
    * This callback is called if something fails.
    *
    * The assigned closure must handle 1 parameter (the error message)
    *
    * If no external Closure is assigned an exception is thrown
    *
    * @type \Closure
    */
   private $_onError;

   // </editor-fold>


   // <editor-fold desc="// – – –   C L A S S   C O N S T A N T S   – – – – – – – – – – – – – – – – – – – – – – –">

   protected const DEFAULT_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/56.0.2924.76 Chrome/56.0.2924.76 Safari/537.36';
   protected const DEFAULT_RAND_COOKIE_FILE_PREFIX = 'meshcl';
   protected const DEFAULT_REQUEST_PARAMS = [
      'url'             => '',
      'post'            => null,
      'headers'         => null,
      'referer'         => '',
      'header'          => false,
      'nobody'          => false,
      'timeout'         => 15,
      'toFile'          => null,
      'attemptsMax'    => 1,
      'attemptsDelay'  => 10,
      'curl'            => [],
   ];

   // </editor-fold>


   // <editor-fold desc="// – – –   P U B L I C   C O N S T R U C T O R   – – – – – – – – – – – – – – – – – – – –">

   /**
    * Client constructor.
    *
    * @throws \Messier\HttpClient\ClientException if no PHP curl extension is active.
    */
   public function __construct()
   {

      if ( ! \function_exists( 'curl_exec' ) )
      {
         throw new ClientException( 'Can not use the Messier\\HttClient\\Client if no curl php extension is enabled!' );
      }

      $this->_curlInfo    = [];

      $this->_onError     = function( $error )
      {
         if ( \is_string( $error ) )
         {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new ClientException( $error );
         }
         else if ( $error instanceof \Throwable )
         {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new ClientException( $error->getMessage(), (int) $error->getCode(), $error );
         }
      };
      $this->_curlHandle = null;

   }

   public function __destruct()
   {

      unset( $this->_curlHandle );

      if ( null !== $this->_cookieFile )
      {
         @\unlink( $this->_cookieFile );
      }

   }

   // </editor-fold>


   // <editor-fold desc="// – – –   P R O T E C T E D   M E T H O D S   – – – – – – – – – – – – – – – – – – – – –">

   protected function _init()
   {

      if ( $this->_useRandomCookieFile )
      {
         $this->_setRandomCookieFile();
      }

   }

   protected function _setRandomCookieFile()
   {

      $this->_cookieFile = \tempnam( \sys_get_temp_dir(), $this->_randCookieFilePrefix );

      if ( null !== $this->_cookieFile )
      {
         \file_put_contents( $this->_cookieFile, '' );
      }

   }

   /**
    * Runs the request and return the response.
    *
    * @param  array $parameters The request parameters.
    * @return string|false Returns the response in the usual case, TRUE if result is a file and FALSE on error.
    */
   protected function _sendRequest( array $parameters )
   {

      $this->_curlInfo = [];

         // Merge defined parameters with default parameters
      $parameters = array_merge( static::DEFAULT_REQUEST_PARAMS, $parameters );

      // close existing curl handle if defined
      $this->_closeCurl();

      // Init the curl handle
      $this->_initCurl( $parameters );

      // If output should be redirected to a file, set the option
      $toFilePointer = null;
      if ( isset( $parameters[ 'toFile' ] ) )
      {

         $toFilePointer = @\fopen( $parameters[ 'toFile' ], 'wb' );

         if ( ! $toFilePointer )
         {
            ( $this->_onError )( 'HTTPClient can not open file "' . $parameters[ 'toFile' ] . '" for writing!' );
            return false;
         }

         \curl_setopt( $this->_curlHandle, CURLOPT_FILE, $toFilePointer );

      }

      // Do the HTTP request while success or error
      do { $response = \curl_exec( $this->_curlHandle ); }
      while ( false === $response &&
              0 !== --$parameters['attemptsMax'] &&
              false !== \sleep( $parameters[ 'attemptsDelay' ] ) );

      // Close to file pointer if defined
      if ( null !== $toFilePointer )
      {
         \fclose( $toFilePointer );
         // delete the to file if no valid response exists
         if ( false === $response )
         {
            \unlink( $parameters[ 'toFile' ] );
         }
      }

      $error = @\curl_error( $this->_curlHandle );

      $this->_curlInfo = @\curl_getinfo( $this->_curlHandle );

      if ( ! empty( $error ) )
      {

         ( $this->_onError )( $error );
         return false;
      }

      if ( false === $response )
      {

         ( $this->_onError )( 'HTTPClient can not get a response from "' . $parameters[ 'url' ] . '"!' );
         return false;
      }

      // Saving response content into lastpageFile
      if ( null !== $this->_lastResponseToFile )
      {
         \file_put_contents( $this->_lastResponseToFile, $response );
      }

      return $response;

   }

   protected function _initCurl( array $parameters )
   {

      // Init the default options
      $options = [
         CURLOPT_URL             => $parameters[ 'url' ],
         CURLOPT_HEADER          => $parameters[ 'header' ],
         CURLOPT_TIMEOUT         => $parameters[ 'timeout' ],
         CURLOPT_NOBODY          => $parameters[ 'nobody' ],
         CURLOPT_USERAGENT       => $this->_userAgent,
         CURLOPT_RETURNTRANSFER  => ! isset( $parameters[ 'toFile' ] ),
         CURLOPT_FOLLOWLOCATION  => 1,
         CURLOPT_ENCODING        => '',
      ];

      // Init referer if defined
      if ( ! empty( $parameters[ 'referer' ] ) )
      {
         $options[ CURLOPT_REFERER ] = $parameters[ 'referer' ];
      }

      // Set Post parameters if defined
      if ( null !== $parameters[ 'post' ] )
      {
         $options[ CURLOPT_POST ]         = 1;
         $options[ CURLOPT_POSTFIELDS ]   = $parameters[ 'post' ];
      }

      // Set extra headers if defined
      if ( null !== $parameters[ 'headers' ] )
      {
         $options[ CURLOPT_HTTPHEADER ] = $parameters[ 'headers' ];
      }

      // Set cookie file if defined
      $cookieFile = $this->getCookieFile();
      if ( null !== $cookieFile )
      {
         $options[ CURLOPT_COOKIEFILE ]   = $cookieFile;
         $options[ CURLOPT_COOKIEJAR ]    = $cookieFile;
      }

      // init curl
      $this->_curlHandle = \curl_init();
      \curl_setopt_array( $this->_curlHandle, $parameters[ 'curl' ] + $options );

   }

   protected function _buildUrl( string $url, array $parameters = [] )
   {

      $this->_nextRequestGetParams = \array_merge( $this->_nextRequestGetParams, $parameters );

      if ( 0 < \count( $this->_nextRequestGetParams ) )
      {
         $url .= '?' . \http_build_query( $this->_nextRequestGetParams );
      }

      $this->_nextRequestGetParams = [];

      return $url;

   }

   protected function _closeCurl()
   {

      if ( null === $this->_curlHandle )
      {
         return;
      }

      @\curl_close( $this->_curlHandle );

      $this->_curlHandle = null;

   }

   // </editor-fold>


   // <editor-fold desc="// – – –   P U B L I C   M E T H O D S   – – – – – – – – – – – – – – – – – – – – – – – –">


   // <editor-fold desc="// – – –   G E T T E R   – – – – – – – – – – – – –">

   /**
    * Gets the user agent string
    *
    * @return string
    */
   public final function getUserAgent() : string
   {

      return $this->_userAgent;

   }

   /**
    * Gets if the client creates a temporary file for cookies.
    *
    * @return boolean
    */
   public final function getUseRandomCookieFile() : bool
   {

      return $this->_useRandomCookieFile;

   }

   /**
    * Gets the Prefix for random cookie file.
    *
    * @return string
    */
   public final function getRandCookieFilePrefix() : string
   {

      return $this->_randCookieFilePrefix;

   }

   /**
    * Gets the path of current used cookie file
    *
    * @return string|null
    */
   public final function getCookieFile() : ?string
   {

      return $this->_cookieFile;

   }

   /**
    * If here a valid file path is defined, the last response is stored inside the file.
    *
    * @return null|string
    */
   public final function getLastResponseTargetFile() : ?string
   {

      return $this->_lastResponseToFile;

   }

   /**
    * Gets information about the last request/transfer.
    *
    * @return array
    */
   public final function getInfo() : array
   {

      return $this->_curlInfo;

   }

   /**
    * Gets the current cookies.
    *
    * @return array
    */
   public final function getCookies() : array
   {

      if ( ! $this->getCookieFile() )
      {
         return [];
      }

      unset( $this->_curlHandle );

      $text = \file_get_contents( $this->getCookieFile() );

      $cookies = [];
      foreach ( \explode( "\n", $text ) as $line )
      {
         $parts = explode( "\t", $line );
         if ( 7 === \count( $parts ) )
         {
            $cookies[ $parts[ 5 ] ] = $parts[ 6 ];
         }
      }

      return $cookies;

   }

   // </editor-fold>


   // <editor-fold desc="// – – –   S E T T E R   – – – – – – – – – – – – –">

   /**
    * Sets the user agent string
    *
    * @param  string $userAgent
    * @return Client
    */
   public function setUserAgent( string $userAgent ) : Client
   {

      $this->_userAgent = $userAgent;

      return $this;

   }

   /**
    * Sets if the client creates a temporary file for cookies.
    *
    * @param  boolean $useRandomCookieFile
    * @return Client
    */
   public function setUseRandomCookieFile( bool $useRandomCookieFile ) : Client
   {

      $this->_useRandomCookieFile = $useRandomCookieFile;

      return $this;

   }

   /**
    * Sets the Prefix for random cookie file.
    *
    * @param  string $randCookieFilePrefix
    * @return Client
    */
   public function setRandCookieFilePrefix( string $randCookieFilePrefix ) : Client
   {

      $this->_randCookieFilePrefix = $randCookieFilePrefix;

      return $this;

   }

   /**
    * Sets the callback, called if something fails.
    *
    * The assigned closure must handle 1 parameter (the error message)
    *
    * If no external Closure is assigned an exception is thrown
    *
    * @param \Closure $errorCallback
    * @return \Messier\HttpClient\Client
    */
   public function onError( \Closure $errorCallback ) : Client
   {

      $this->_onError = $errorCallback;

      return $this;

   }

   /**
    * If here a valid file path is defined, the last response is stored inside the file.
    *
    * @param  null|string $lastResponseToFile
    * @return Client
    */
   public function setLastResponseToFile( ?string $lastResponseToFile ) : Client
   {

      $this->_lastResponseToFile = $lastResponseToFile ?? '';

      if ( '' === \trim( $this->_lastResponseToFile ) ) { $this->_lastResponseToFile = null; }

      return $this;

   }

   /**
    * Sets the GET parameters, usable with next called request. After next request this parameter is emptied.
    *
    * @param array $getParameters
    * @return \Messier\HttpClient\Client
    */
   public function setGetParameters( array $getParameters = [] ) : Client
   {

      $this->_nextRequestGetParams = $getParameters;

      return $this;

   }

   // </editor-fold>


   // <editor-fold desc="// – – –   O T H E R   – – – – – – – – – – – – – -">

   /**
    * Executes a HEAD HTTP request to get all response headers.
    *
    * If no external error handler is defined, on error here an ClientException is thrown
    *
    * @param string $url        The request url.
    * @param array  $parameters Optional GET URL parameters.
    * @param array  $options    Known options are: headers, referer, timeout, toFile, attemptsMax, attemptsDelay
    * @return string|boolean    Returns a string on success or FALSE on error
    */
   public function sendHead( string $url, array $parameters = [], array $options = [] )
   {

      // The request URL
      $options[ 'url' ]    = $this->_buildUrl( $url, $parameters );
      // Getting the headers
      $options[ 'header' ] = true;
      // Get no body
      $options[ 'nobody' ] = true;

      return $this->_sendRequest( $options );

   }

   /**
    * Executes GET HTTP request.
    *
    * If no external error handler is defined, on error here an ClientException is thrown
    *
    * @param string $url        The request url.
    * @param array  $parameters Optional GET URL parameters.
    * @param array  $options    Known options are: headers, referer, timeout, toFile, attemptsMax, attemptsDelay
    * @return string|boolean    Returns a string on success or FALSE on error
    */
   public function sendGet( string $url, array $parameters = [], array $options = [] )
   {

      // The request URL
      $options[ 'url' ]    = $this->_buildUrl( $url, $parameters );

      return $this->_sendRequest( $options );

   }

   /**
    * Executes a POST HTTP request.
    *
    * If no external error handler is defined, on error here an ClientException is thrown
    *
    * @param string $url     The request url.
    * @param array  $data    The POST data array.
    * @param array  $options Known options are: headers, referer, timeout, toFile, attemptsMax, attemptsDelay
    * @return string|boolean Returns a string on success, TRUE on successful file transfer or FALSE on error
    */
   public function sendPost( string $url, array $data = [], array $options = [] )
   {

      $options[ 'url' ]  = $this->_buildUrl( $url );
      $options[ 'post' ] = $data;

      return $this->_sendRequest( $options );

   }


   /**
    * Downloads file to specified destination file.
    *
    * If no external error handler is defined, on error here an ClientException is thrown
    *
    * @param  string $url             The request url.
    * @param  string $destinationFile file destination.
    * @param  array  $options    Known options are: headers, referer, timeout, attemptsMax, attemptsDelay
    * @return boolean                 Return TRUE on success, FALSE otherwise
    */
   public function sendDownload( string $url, string $destinationFile, array $options = [] )
   {

      $options[ 'url' ]    = $this->_buildUrl( $url );
      $options[ 'toFile' ] = $destinationFile;

      return $this->_sendRequest( $options );

   }

   // </editor-fold>


   // </editor-fold>


   // <editor-fold desc="// – – –   P U B L I C   S T A T I C   M E T H O D S   – – – – – – – – – – – – – – – – –">

   /**
    * Creates a empty client instance.
    *
    * @return \Messier\HttpClient\Client
    * @throws \Messier\HttpClient\ClientException
    */
   public static function Create() : Client
   {

      return new Client();

   }

   // </editor-fold>


}

