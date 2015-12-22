<?php

use InstallSmtp;

class MailInbox implements \RocketSled\Runnable
{

    private $file_name;
    private $template;

    public function __construct ()
    {
        $this->file_name = (isset ( $_GET[ 'mail_id' ] )) ? "read_mail.html" : "mailbox.html";
    }

    public function run ()
    {
        $this->template = $this->load_template ( $this->file_name , TRUE );
        if ( isset ( $_GET[ 'mail_id' ] ) )
            $this->prepare_mail_template ();
        else
            $this->prepare_inbox_template ();
        die ( $this->template->html () );
    }

    private function read_log_file ()
    {
        $path = dirname ( __FILE__ ) . "/../smtp.log";
        $data_array = array();
        $count = 0;
        $handle = @fopen ( $path , "r" );
        $text_content = FALSE;
        $html_content = FALSE;
        $boundary = '';
        if ( $handle )
        {
            while ( ($buffer = fgets ( $handle , 4096 )) !== false )
            {
                $buffer = ($this->startsWith ( $buffer , 'onread:' )) ? str_replace ( 'onread: ' , '' , $buffer ) : $buffer;
                $buffer = ($this->startsWith ( $buffer , 'onwrite:' )) ? str_replace ( 'onwrite: ' , '' , $buffer ) : $buffer;
                if ( $this->startsWith ( trim ( $buffer ) , '250 Ok:' ) )
                {
                    $count++;
                    $text_content = FALSE;
                    $html_content = FALSE;
                    continue;
                }
                $data_array[ $count ][ 'Date' ] = ($this->startsWith ( $buffer , 'Date: ' )) ? explode ( "Date:" , $buffer )[ 1 ] : $data_array[ $count ][ 'Date' ];
                $data_array[ $count ][ 'Subject' ] = ($this->startsWith ( $buffer , 'Subject: ' )) ? explode ( "Subject: " , $buffer )[ 1 ] : $data_array[ $count ][ 'Subject' ];
                $data_array[ $count ][ 'Date' ] = ($this->startsWith ( $buffer , 'Date: ' )) ? explode ( "Date:" , $buffer )[ 1 ] : $data_array[ $count ][ 'Date' ];
                $buffer = ($this->startsWith ( $buffer , 'From:' )) ? str_replace ( "<" , "" , str_replace ( ">" , "" , $buffer ) ) : $buffer;
                $data_array[ $count ][ 'From' ] = ($this->startsWith ( $buffer , 'From:' )) ? explode ( ":" , $buffer )[ 1 ] : $data_array[ $count ][ 'From' ];
                $data_array[ $count ][ 'To' ] = ($this->startsWith ( $buffer , 'To:' )) ? explode ( ":" , $buffer )[ 1 ] : $data_array[ $count ][ 'To' ];
                $data_array[ $count ][ 'Bcc' ] = ($this->startsWith ( $buffer , 'Bcc:' )) ? explode ( ":" , $buffer )[ 1 ] : $data_array[ $count ][ 'Bcc' ];
                $boundary = ($this->startsWith ( trim ( $buffer ) , 'boundary=' )) ? str_replace ( '"' , "" , explode ( 'boundary="' , $buffer )[ 1 ] ) : $boundary;
                if ( $this->startsWith ( $buffer , 'Content-Type:' ) && (trim ( explode ( "Content-Type: " , $buffer )[ 1 ] ) == 'text/plain; charset=utf-8' ) )
                {
                    $text_content = TRUE;
                    continue;
                }
                if ( $this->startsWith ( $buffer , 'Content-Type:' ) && (trim ( explode ( "Content-Type: " , $buffer )[ 1 ] ) == 'text/html; charset=utf-8' ) )
                {
                    $html_content = TRUE;
                    $text_content = FALSE;
                    continue;
                }
                if ( $text_content && (trim ( $buffer ) !== "Content-Transfer-Encoding: quoted-printable") )
                {
                    if ( strpos ( trim ( $buffer ) , trim ( $boundary ) ) )
                    {
                        continue;
                    }
                    $buffer = trim ( str_replace ( "=" , "" , $buffer ) );
                    $data_array[ $count ][ 'text' ].=$buffer;
                }
                if ( $html_content && (trim ( $buffer ) !== "Content-Transfer-Encoding: quoted-printable") )
                {
                    if ( strpos ( trim ( $buffer ) , trim ( $boundary ) ) )
                    {
                        continue;
                    }
                    $buffer = substr ( trim ( $buffer ) , 0 , -1 );
                    $data_array[ $count ][ 'html' ].=$buffer;
                }
            }
        }
        if ( !feof ( $handle ) )
        {
            echo "Error: unexpected fgets() fail\n";
        }
        fclose ( $handle );
        return $data_array;
    }

    private function load_template ( $file_name , $render )
    {
        try
        {
            $surl = (isset ( $_SERVER[ 'HTTPS' ] )) ? 'https://' : 'http://';
            $surl .= isset ( $_SERVER[ 'SERVER_NAME' ] ) ? $_SERVER[ 'SERVER_NAME' ] : 'localhost';
            $path = pathinfo ( $_SERVER[ 'PHP_SELF' ] );
            $surl .= $path[ 'dirname' ] . '/';
            $service_base = $surl . "../FakeSmtp/mailbox";
            $this->file_path = dirname ( __FILE__ ) . "/" . $file_name;
            $apppaths = new \DOMTemplateAppPaths ( ($render) ? Fragmentify::render ( $this->file_path ) : $this->file_path , $service_base , TRUE );
            $template = $apppaths->process ()->template ();
            $imgpaths = new \DOMTemplateImgPaths ( $template , $this->file_path );
            $template = $imgpaths->process ()->template ();
            return $template;
        }
        catch ( Exception $e )
        {
            throw $e;
        }
    }

    private function startsWith ( $haystack , $needle )
    {
        return $needle === "" || strrpos ( $haystack , $needle , -strlen ( $haystack ) ) !== FALSE;
    }

    private function endsWith ( $haystack , $needle )
    {
        return $needle === "" || (($temp = strlen ( $haystack ) - strlen ( $needle )) >= 0 && strpos ( $haystack , $needle , $temp ) !== FALSE);
    }

    private function remove_last_repeating_element ( $template , $ending_flag , $parent_count = 1 , $previous_count = 0 , $next_count = 0 )
    {
        $item = $template->query ( $ending_flag )->item ( 0 );
        $proto = $item->parentNode;
        for ( $i = 1; $i < $parent_count; $i ++ )
        {
            $proto = $proto->parentNode;
        }
        for ( $i = 1; $i <= $previous_count; $i ++ )
        {
            $item = $item->previousSibling;
        }
        for ( $i = 1; $i <= $next_count; $i ++ )
        {
            $item = $item->nextSibling;
        }
        $proto->removeChild ( $item );
    }

    private function prepare_inbox_template ()
    {
        $mail_list = $this->read_log_file ();
        $this->template->remove ( '.static' );
        $this->template->setValue ( '.inbox_form@action' , '?r=MailInbox' );
        $this->template->setValue ( '.inbox_form/input@value' , json_encode ( $mail_list ) );
        $repeate_row = $this->template->repeat ( ".repeate_row" );
        $count = 0;
        $index = 0;
        foreach ( $mail_list as $mail )
        {
            if ( isset ( $mail[ "Bcc" ] ) )
            {
                $index++;
                continue;
            }
            if ( isset ( $mail[ 'Subject' ] ) && isset ( $mail[ 'Date' ] ) && isset ( $mail[ 'From' ] ) )
            {
                $repeate_row->setValue ( ".mailbox-date" , date ( 'Y-m-d h:m:s' , strtotime ( $mail[ 'Date' ] ) ) );
                $repeate_row->setValue ( ".subject" , $mail[ 'Subject' ] );
                $repeate_row->setValue ( ".mailbox-value" , $count + 1 );
                $repeate_row->setValue ( ".mailbox-data/input@value" , $index );
                $repeate_row->setValue ( ".email_from" , $mail[ 'From' ] );
                $repeate_row->next ();
                $count++;
                $index++;
            }
        }
        $this->template->setValue ( '.total_message' , $count . ' Messages' );
        $this->remove_last_repeating_element ( $this->template , ".stop_row_flag" , 1 , 2 , 0 );
    }

    private function prepare_mail_template ()
    {
        $mail_index = $_GET [ 'mail_id' ];
        $mail_array = $this->read_log_file ();

        $mail_data = $mail_array[ $mail_index ];
        $this->template->remove ( '.static' );
        $this->template->setValue ( '.inbox_link@href' , '?r=MailInbox' );
        $this->template->setValue ( '.mailbox-read-info/h3' , $mail_data[ 'Subject' ] );
        $this->template->setValue ( '.mailbox-read-info/h5' , "From :" . $mail_data[ 'From' ] );
        $this->template->setValue ( '.mailbox-read-message' , $mail_data[ 'html' ] );
        $this->template->setValue ( '.total_message' , $_GET[ 'number' ] . ' Messages' );
    }

}

?>
