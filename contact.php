<?php
function ValidateEmail($email)
{
   $pattern = '/^([0-9a-z]([-.\w]*[0-9a-z])*@(([0-9a-z])+([-\w]*[0-9a-z])*\.)+[a-z]{2,6})$/i';
   return preg_match($pattern, $email);
}
function ReplaceVariables($code)
{
   foreach ($_POST as $key => $value)
   {
      if (is_array($value))
      {
         $value = implode(",", $value);
      }
      $name = "$" . $key;
      $code = str_replace($name, $value, $code);
   }
   $code = str_replace('$ipaddress', $_SERVER['REMOTE_ADDR'], $code);
   return $code;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['formid']) && $_POST['formid'] == 'form1')
{
   $mailto = 'foxhuyops@gmail.com';
   $mailfrom = isset($_POST['email']) ? $_POST['email'] : $mailto;
   $subject = 'Запрос подбор';
   $message = 'текст письма';
   $success_url = './yes.html';
   $error_url = './no.html';
   $csvFile = "./formdata.csv";
   $eol = "\n";
   $error = '';
   $internalfields = array ("submit", "reset", "send", "filesize", "formid", "captcha", "recaptcha_challenge_field", "recaptcha_response_field", "g-recaptcha-response", "h-captcha-response");
   $logdata = '';
   $max_filesize = 1000*1024;
   $upload_folder = "upload";
   $upload_folder = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'])."/".$upload_folder;
   $boundary = md5(uniqid(time()));
   $header  = 'From: '.$mailfrom.$eol;
   $header .= 'Reply-To: '.$mailfrom.$eol;
   $header .= 'MIME-Version: 1.0'.$eol;
   $header .= 'Content-Type: multipart/mixed; boundary="'.$boundary.'"'.$eol;
   $header .= 'X-Mailer: PHP v'.phpversion().$eol;
   try
   {
      if (!ValidateEmail($mailfrom))
      {
         $error .= "The specified email address (" . $mailfrom . ") is invalid!\n<br>";
         throw new Exception($error);
      }
      $prefix = rand(111111, 999999);
      $file_count = 0;
      foreach ($_FILES as $key => $value)
      {
         if (is_array($_FILES[$key]['name']))
         {
            $count = count($_FILES[$key]['name']);
            for ($file = 0; $file < $count; $file++)
            {
               if ($_FILES[$key]['name'][$file] != "" and file_exists($_FILES[$key]['tmp_name'][$file]) and $_FILES[$key]['size'][$file] > 0)
               {
                  $upload_DstName[$file_count] = $prefix . "_" . str_replace(" ", "_", $_FILES[$key]['name'][$file]);
                  $upload_SrcName[$file_count] = $_FILES[$key]['name'][$file];
                  $upload_Size[$file_count] = $_FILES[$key]['size'][$file];
                  $upload_Temp[$file_count] = $_FILES[$key]['tmp_name'][$file];
                  $upload_URL[$file_count] = "$upload_folder/$upload_DstName[$file_count]";
                  $upload_FieldName[$file_count] = $key;
                  $file_count++;
               }
            }
         }
         else
         if ($_FILES[$key]['name'] != "" and file_exists($_FILES[$key]['tmp_name']) and $_FILES[$key]['size'] > 0)
         {
            $upload_DstName[$file_count] = $prefix . "_" . str_replace(" ", "_", $_FILES[$key]['name']);
            $upload_SrcName[$file_count] = $_FILES[$key]['name'];
            $upload_Size[$file_count] = $_FILES[$key]['size'];
            $upload_Temp[$file_count] = $_FILES[$key]['tmp_name'];
            $upload_URL[$file_count] = "$upload_folder/$upload_DstName[$file_count]";
            $upload_FieldName[$file_count] = $key;
            $file_count++;
         }
      }
      for ($i = 0; $i < $file_count; $i++)
      {
         if ($upload_Size[$i] >= $max_filesize)
         {
            $error .= "The size of $key (file: $upload_SrcName[$i]) is bigger than the allowed " . $max_filesize/1024 . " Kbytes!\n";
            throw new Exception($error);
         }
      }
      $uploadfolder = basename($upload_folder);
      for ($i = 0; $i < $file_count; $i++)
      {
         $uploadFile = $uploadfolder . "/" . $upload_DstName[$i];
         if (!is_dir($uploadfolder) || !is_writable($uploadfolder))
         {
            $error = 'Upload directory is not writable, or does not exist.';
            throw new Exception($error);
         }
         move_uploaded_file($upload_Temp[$i] , $uploadFile);
         $name = "$" . $upload_FieldName[$i];
         $message = str_replace($name, $upload_URL[$i], $message);
      }
      $message .= $eol;
      $message .= "IP Address : ";
      $message .= $_SERVER['REMOTE_ADDR'];
      $message .= $eol;
      $message .= "Referer : ";
      $message .= $_SERVER['SERVER_NAME'];
      $message .= $_SERVER['PHP_SELF'];
      $message .= $eol;
      foreach ($_POST as $key => $value)
      {
         if (!in_array(strtolower($key), $internalfields))
         {
            $logdata .= ',';
            if (is_array($value))
            {
               $message .= ucwords(str_replace("_", " ", $key)) . " : " . implode(",", $value) . $eol;
               $logdata .= implode("|", $value);
            }
            else
            {
               $message .= ucwords(str_replace("_", " ", $key)) . " : " . $value . $eol;
               $value = str_replace(",", " ", $value);
               $logdata .= $value;
            }
         }
      }
      $logdata = str_replace("\r", "", $logdata);
      $logdata = str_replace("\n", " ", $logdata);
      $logdata .= "\r\n";
      $handle = fopen($csvFile, 'a') or die("can't open file");
      $logtime = date("Y-m-d H:i:s,");
      fwrite($handle, $logtime);
      fwrite($handle, $_SERVER['REMOTE_ADDR']);
      fwrite($handle, $logdata);
      fclose($handle);
      if ($file_count > 0)
      {
         $message .= "\nThe following files have been uploaded:\n";
         for ($i = 0; $i < $file_count; $i++)
         {
            $message .= $upload_SrcName[$i] . ": " . $upload_URL[$i] . "\n";
         }
      }
      $body  = 'This is a multi-part message in MIME format.'.$eol.$eol;
      $body .= '--'.$boundary.$eol;
      $body .= 'Content-Type: text/plain; charset=UTF-8'.$eol;
      $body .= 'Content-Transfer-Encoding: 8bit'.$eol;
      $body .= $eol.stripslashes($message).$eol;
      $body .= '--'.$boundary.'--'.$eol;
      if ($mailto != '')
      {
         mail($mailto, $subject, $body, $header);
      }
      $successcode = file_get_contents($success_url);
      $successcode = ReplaceVariables($successcode);
      echo $successcode;
   }
   catch (Exception $e)
   {
      $errorcode = file_get_contents($error_url);
      $replace = "##error##";
      $errorcode = str_replace($replace, $e->getMessage(), $errorcode);
      echo $errorcode;
   }
   exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['formid']) && $_POST['formid'] == 'form2')
{
   $mailto = 'yourname@yourdomain.com';
   $mailfrom = isset($_POST['email']) ? $_POST['email'] : $mailto;
   $subject = 'Website form';
   $message = 'Values submitted from web site form:';
   $success_url = '';
   $error_url = '';
   $eol = "\n";
   $error = '';
   $internalfields = array ("submit", "reset", "send", "filesize", "formid", "captcha", "recaptcha_challenge_field", "recaptcha_response_field", "g-recaptcha-response", "h-captcha-response");
   $boundary = md5(uniqid(time()));
   $header  = 'From: '.$mailfrom.$eol;
   $header .= 'Reply-To: '.$mailfrom.$eol;
   $header .= 'MIME-Version: 1.0'.$eol;
   $header .= 'Content-Type: multipart/mixed; boundary="'.$boundary.'"'.$eol;
   $header .= 'X-Mailer: PHP v'.phpversion().$eol;
   try
   {
      if (!ValidateEmail($mailfrom))
      {
         $error .= "The specified email address (" . $mailfrom . ") is invalid!\n<br>";
         throw new Exception($error);
      }
      $message .= $eol;
      $message .= "IP Address : ";
      $message .= $_SERVER['REMOTE_ADDR'];
      $message .= $eol;
      foreach ($_POST as $key => $value)
      {
         if (!in_array(strtolower($key), $internalfields))
         {
            if (is_array($value))
            {
               $message .= ucwords(str_replace("_", " ", $key)) . " : " . implode(",", $value) . $eol;
            }
            else
            {
               $message .= ucwords(str_replace("_", " ", $key)) . " : " . $value . $eol;
            }
         }
      }
      $body  = 'This is a multi-part message in MIME format.'.$eol.$eol;
      $body .= '--'.$boundary.$eol;
      $body .= 'Content-Type: text/plain; charset=UTF-8'.$eol;
      $body .= 'Content-Transfer-Encoding: 8bit'.$eol;
      $body .= $eol.stripslashes($message).$eol;
      if (!empty($_FILES))
      {
         foreach ($_FILES as $key => $value)
         {
             if ($_FILES[$key]['error'] == 0)
             {
                $body .= '--'.$boundary.$eol;
                $body .= 'Content-Type: '.$_FILES[$key]['type'].'; name='.$_FILES[$key]['name'].$eol;
                $body .= 'Content-Transfer-Encoding: base64'.$eol;
                $body .= 'Content-Disposition: attachment; filename='.$_FILES[$key]['name'].$eol;
                $body .= $eol.chunk_split(base64_encode(file_get_contents($_FILES[$key]['tmp_name']))).$eol;
             }
         }
      }
      $body .= '--'.$boundary.'--'.$eol;
      if ($mailto != '')
      {
         mail($mailto, $subject, $body, $header);
      }
      header('Location: '.$success_url);
   }
   catch (Exception $e)
   {
      $errorcode = file_get_contents($error_url);
      $replace = "##error##";
      $errorcode = str_replace($replace, $e->getMessage(), $errorcode);
      echo $errorcode;
   }
   exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Запрос</title>
<meta name="generator" content="WYSIWYG Web Builder 11 - http://www.wysiwygwebbuilder.com">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="setting_test_plan_project_gear_check_management_icon_230468.ico" rel="shortcut icon" type="image/x-icon">
<link href="icon.png" rel="icon" sizes="1466x1212" type="image/png">
<link href="gratis-png-taller-mecanico-automotriz-automotriz-automotriz.png" rel="icon" sizes="890x831" type="image/png">
<link href="css/PODBOR102.css" rel="stylesheet">
<link href="css/contact.css" rel="stylesheet">
<script src="jquery-3.6.0.min.js"></script>
<script src="wwb17.min.js"></script>
<script>   
   $(document).ready(function()
   {
      $("#Form1").submit(function(event)
      {
         return true;
      });
   });
</script>
</head>
<body>
   <div id="wb_Form1">
      <form name="contact" method="post" action="<?php echo basename(__FILE__); ?>" enctype="multipart/form-data" target="_blank" id="Form1">
         <input type="hidden" name="formid" value="form1">
         <input type="hidden" name="Дата" value="Data">
         <div id="wb_Form2">
            <form name="contact" method="post" action="<?php echo basename(__FILE__); ?>" enctype="multipart/form-data" id="Form2">
               <input type="hidden" name="formid" value="form2">
               <label for="Editbox1" id="Label1">Имя</label>
               <input type="text" id="Editbox1" name="name" value="" spellcheck="false" placeholder="Имя">
               <label for="Editbox6" id="Label2">VIN</label>
               <input type="number" id="Editbox6" name="code" value="" spellcheck="false" placeholder="Телефон">
               <input type="text" id="Editbox7" name="phone" value="" spellcheck="false" placeholder="VIN/Frame">
               <label for="Editbox8" id="Label4">Email:</label>
               <input type="text" id="Editbox8" name="email" value="" spellcheck="false" placeholder="e-mail">
               <label for="Editbox9" id="Label5">Запчасти</label>
               <input type="submit" id="Button2" name="send" value="Отправить">
               <input type="reset" id="Button3" name="Reset" value="Сброс">
               <label for="Editbox7" id="Label3">Телефон</label>
               <input type="text" id="Editbox9" name="Editbox9" value="" spellcheck="false">
            </form>
         </div>
         <a id="Button1" href="./index.html">Назад</a>
      </form>
   </div>
</body>
</html>