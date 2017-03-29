<?php
/**
 * @author     Messier 1001 <messier.1001+code@gmail.com>
 * @copyright  ©2017, Messier 1001
 * @package    Messier\HttpClient
 * @since      2017-03-29
 * @version    0.1.0
 */


declare( strict_types = 1 );


namespace Messier\HttpClient;


/**
 * Defines a class that …
 *
 * @since v0.1.0
 */
class ClientException extends \Exception
{


   // <editor-fold desc="// – – –   P U B L I C   C O N S T R U C T O R   – – – – – – – – – – – – – – – – – – – –">


   public function __construct( string $message, int $code = 0, ?\Throwable $previous = null )
   {

      parent::__construct( $message, $code, $previous );

   }

   // </editor-fold>


}

