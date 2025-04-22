<?php




/**
 * 
 * @global string   MAIL_SERVER
 * @global string   MAIL_USER
 * @global string   MAIL_PASS
 * @global string   MAIL_SMTP_SECURE
 * @global int      MAIL_PORT
 * @global int      IMAP_PORT
 * @global string   IMAP_SENT
 * @global int      IMAP_APPEND_STATUS
 * @global int      GLOBEMAIL_SMTPDEBUG
 * @global int      NUM_DAYS_DELETE_OBSOLETE_EMAILS
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
     * @global PDO $pdo
     * @global int NUM_DAYS_DELETE_OBSOLETE_EMAILS
     * @static
     * @return void
     */
    public static function db_deleteObsolete() {

        global $pdo;

        $query = 'DELETE FROM `' . __CLASS__ . '` WHERE timeCreated < DATE_SUB( NOW(), INTERVAL ' . NUM_DAYS_DELETE_OBSOLETE_EMAILS . ' DAY);';

        $stmt = $pdo->prepare( $query );

        $stmt->execute();

    }

    /**
     * @static
     * @var string $emailAddress
     * @return string
     */
    public static function create_identifier( $emailAddress ) {

        return md5( time() . $emailAddress );

    }

    /**
     * @global int IMAP_APPEND_STATUS
     * @global string MAIL_SERVER
     * @global int IMAP_PORT
     * @global string MAIL_USER
     * @global string MAIL_PASS
     * @return array|null
     */
    public static function get_mailbox_sizes() {

        if ( defined( 'IMAP_APPEND_STATUS' ) === false || IMAP_APPEND_STATUS !== 1 ) {

            return null;

        }

        $hostname = "{" . MAIL_SERVER . ":" . IMAP_PORT . "/imap/ssl/novalidate-cert}";

        $imap = imap_open( $hostname, MAIL_USER, MAIL_PASS );

        if ( $imap === false ) {

            return null;

        }

        $mailboxes = imap_list( $imap, $hostname, '*' );

        if ( $mailboxes === false ) {

            imap_close( $imap );

            return null;

        }

        $data = [];

        foreach ( $mailboxes as $mailbox ) {

            if ( imap_reopen( $imap, $mailbox ) === false ) {

                continue;

            }

            $numMessages = imap_num_msg( $imap );

            if ( $numMessages === false ) {

                continue;

            }

            $folderSize = 0;

            for ( $i = 1; $i <= $numMessages; $i++ ) {

                $header = imap_fetchheader( $imap, $i );

                if ( $header === false ) {

                    continue;

                }

                $body = imap_body( $imap, $i );

                if ( $body === false ) {

                    continue;

                }

                $size = strlen( $header ) + strlen( $body );

                $folderSize += $size;

            }

            $data[] = [
                'mailbox' => str_replace( $hostname, '', $mailbox ),
                'num' => $numMessages,
                'size' => $folderSize
            ];

        }

        imap_close( $imap );

        if ( count( $data ) > 0 ) {

            return $data;

        } else {

            return null;

        }

    }

    /**
     * @global int IMAP_APPEND_STATUS
     * @global string MAIL_SERVER
     * @global int IMAP_PORT
     * @global string MAIL_USER
     * @global string MAIL_PASS
     * @return array|null
     */
    public static function get_quota_root() {

        if ( defined( 'IMAP_APPEND_STATUS' ) === false || IMAP_APPEND_STATUS !== 1 ) {

            return null;

        }

        $imapPath = "{" . MAIL_SERVER . ":" . IMAP_PORT . "/imap/ssl/novalidate-cert}";

        $imapStream = imap_open( $imapPath, MAIL_USER, MAIL_PASS );

        if ( $imapStream === false ) {

            return null;

        }

        $quotaInfo = imap_get_quotaroot( $imapStream, "INBOX" );

        $toReturn = [];

        if ( $quotaInfo && isset( $quotaInfo[ 'STORAGE' ] ) ) {

            if ( isset( $quotaInfo[ 'STORAGE' ][ 'usage' ] ) ) {

                $toReturn[] = $quotaInfo[ 'STORAGE' ][ 'usage' ];

            }

            if ( isset( $quotaInfo[ 'STORAGE' ][ 'limit' ] ) ) {

                $toReturn[] = $quotaInfo[ 'STORAGE' ][ 'limit' ];

            }

        }

        imap_close( $imapStream );

        if ( count( $toReturn ) === 2 ) {

            return $toReturn;

        } else {

            return null;

        }

    }



    
    /**
     * @uses PHPMailer
     * @global string MAIL_SERVER
     * @global string MAIL_USER
     * @global string MAIL_PASS
     * @global string MAIL_SMTP_SECURE
     * @global int MAIL_PORT
     * @global int GLOBEMAIL_SMTPDEBUG
     * @return glob_emailSent
     */
    public function __construct() {

        $this->mail = new PHPMailer\PHPMailer\PHPMailer( true );
        $this->mail->isSMTP();
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Host = MAIL_SERVER;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = MAIL_USER;
        $this->mail->Password = MAIL_PASS;
        $this->mail->SMTPSecure = MAIL_SMTP_SECURE;
        $this->mail->Port = MAIL_PORT;
        $this->mail->setFrom( MAIL_USER );
        $this->mail->addReplyTo( MAIL_USER );
        $this->mail->isHTML( true );
        $this->mail->addCustomHeader( 'Accept-Language', 'el-GR, en-US' );
        $this->mail->addCustomHeader( 'Content-Language', 'el-GR' );
        $this->mail->XMailer = ' ';

        if ( defined( 'GLOBEMAIL_SMTPDEBUG' ) ) {

            $this->mail->SMTPDebug = GLOBEMAIL_SMTPDEBUG;

        } else {

            $this->mail->SMTPDebug = 3;

        }
        
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

        if ( defined( 'NUM_DAYS_DELETE_OBSOLETE_EMAILS' ) ) {

            if ( mt_rand( 1, 100 ) === 1 ) {

                self::db_deleteObsolete();

            }

        }

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

        $addresses = [];

        for ( $i = 0 ; $i < count( $emails ) ; $i++ ) {

            $this->mail->addAddress( $emails[ $i ] );

            $addresses[] = $emails[ $i ];
    
        }

        if ( count( $addresses ) === 1 ) {

            $this->address = $addresses[ 0 ];

        } else {

            $this->address = json_encode( $addresses );

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