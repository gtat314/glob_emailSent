<?php




/**
 * @global string   MAIL_SERVER
 * @global string   MAIL_USER
 * @global string   MAIL_PASS
 * @global string   MAIL_SMTP_SECURE
 * @global int      MAIL_PORT
 * @global int      IMAP_PORT
 * @global string   IMAP_SENT
 * @global int      IMAP_APPEND_STATUS
 */
class glob_emailSent extends glob_dbaseTablePrimary {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $timeCreated;

    /**
     * @var string|null
     */
    public $address;

    /**
     * @var string|null
     */
    public $reason;

    /**
     * @var string|null
     */
    public $identifier;

    /**
     * @var string|null
     */
    public $htmlBody;

    /**
     * @var string|null
     */
    public $altBody;

    /**
     * @var string|null
     */
    public $environment;

    /**
     * @var string|null
     */
    public $phpmailer;

    /**
     * @var string|null
     */
    public $debugStr;




    /**
     * @var PHPMailer
     */
    private $mail;




    /**
     * @static
     * @var string $emailAddress
     * @return string
     */
    public static function create_identifier( $emailAddress ) {

        return md5( time() . $emailAddress );

    }



    
    /**
     * @uses PHPMailer
     * @global string MAIL_SERVER
     * @global string MAIL_USER
     * @global string MAIL_PASS
     * @global string MAIL_SMTP_SECURE
     * @global int MAIL_PORT
     * @return glob_emailSent
     */
    public function __construct() {

        $this->mail = new PHPMailer\PHPMailer\PHPMailer( true );
        $this->mail->SMTPDebug = 3;
        $this->mail->isSMTP();
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Host = MAIL_SERVER;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = MAIL_USER;
        $this->mail->Password = MAIL_PASS;
        $this->mail->SMTPSecure = MAIL_SMTP_SECURE;
        $this->mail->Port = MAIL_PORT;
        $this->mail->setFrom( MAIL_USER );
        $this->mail->isHTML( true );
        $this->mail->addCustomHeader( 'Accept-Language', 'el-GR, en-US' );
        $this->mail->addCustomHeader( 'Content-Language', 'el-GR' );
        $this->mail->XMailer = ' ';
        
        $this->mail->Debugoutput = function( $str, $level ) {

            $this->debugStr .= "$level: $str\n";

        };

    }

    /**
     * @param boolean $bool
     * @return glob_emailSent
     */
    public function isHTML( $bool ) {

        $this->mail->isHTML( $bool );

        return $this;

    }

    /**
     * @return void
     */
    public function log() {

        $environment = [
            'SERVER' => $_SERVER,
            'POST' => $_POST,
            'GET' => $_GET
        ];

        $this->environment = json_encode( $environment );
        $this->phpmailer = json_encode( $this->mail );

        parent::db_insert();

    }

    /**
     * @return glob_emailSent
     */
    public function send() {

        try {

            $this->mail->send();

            $this->log();
    
        } catch( Exception $e ) {

            $this->log();
    
            throw new Exception( $e->getMessage(), 1000 );
    
        }

        return $this;

    }

    /**
     * @global string MAIL_SERVER
     * @global int IMAP_PORT
     * @global string IMAP_SENT
     * @global string MAIL_USER
     * @global string MAIL_PASS
     * @global int IMAP_APPEND_STATUS
     * @throws Exception
     * @return glob_emailSent
     */
    public function imapAppend() {

        if ( defined( 'IMAP_APPEND_STATUS' ) === false || IMAP_APPEND_STATUS !== 1 ) {

            return $this;

        }

        $imapPath = "{" . MAIL_SERVER . ":" . IMAP_PORT . "/imap/ssl/novalidate-cert}" . IMAP_SENT;

        $imapStream = imap_open( $imapPath, MAIL_USER, MAIL_PASS );

        if ( $imapStream === false ) {

            throw new Exception( 'Failed to access imap server', 2000 );
    
        }

        $result = imap_append( $imapStream, $imapPath, $this->mail->getSentMIMEMessage() );

        if ( $result === false ) {

            $imapErrors = imap_errors();

            if ( strpos( $imapErrors[ 0 ], 'OVERQUOTA' ) !== false ) {

                throw new Exception( 'Mailbox is full', 2001 );

            } else {

                throw new Exception( 'Failed to import to imap server', 2002 );

            }
    
        }

        imap_close( $imapStream );

        return $this;

    }

    /**
     * @param string $altBody
     * @return glob_emailSent
     */
    public function setAltBody( $altBody ) {

        $this->mail->AltBody = $altBody;

        $this->altBody = $altBody;

        return $this;

    }

    /**
     * @param string $body
     * @return glob_emailSent
     */
    public function setBody( $body ) {

        $this->mail->Body = $body;

        $this->htmlBody = $body;

        return $this;

    }

    /**
     * @param string $subject
     * @return glob_emailSent
     */
    public function setSubject( $subject ) {

        $this->mail->Subject = $subject;

        return $this;

    }

    /**
     * @param string $email
     * @return glob_emailSent
     */
    public function addAddress( $email ) {

        $this->address = $email;

        $this->mail->addAddress( $email );

        return $this;

    }

    /**
     * @param string[] $emails
     * @return glob_emailSent
     */
    public function addAddressMultiple( $emails ) {

        for ( $i = 0 ; $i < count( $emails ) ; $i++ ) {

            $this->mail->addAddress( $emails[ $i ] );
    
        }

        return $this;

    }

    /**
     * @param string $image
     * @param string $alias
     * @return glob_emailSent
     */
    public function addEmbeddedImage( $image, $alias ) {

        $this->mail->AddEmbeddedImage( $image, $alias );

        return $this;

    }

    /**
     * @param string $file
     * @param string $filename
     * @return glob_emailSent
     */
    public function addAttachment( $file, $filename ) {

        $this->mail->AddAttachment( $file, $filename );

        return $this;

    }

    /**
     * @param string $reason
     * @return glob_emailSent
     */
    public function setReason( $reason ) {

        $this->reason = $reason;

        return $this;

    }

    /**
     * @param string $identifier
     * @return glob_emailSent
     */
    public function setIdentifier( $identifier ) {

        $this->identifier = $identifier;

        return $this;

    }

}