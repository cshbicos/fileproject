<?php

class FileHandler
{
   private $user;


   public function __construct(&$user)
   {
      $this->user = $user;
   }

   public function upload($file, $type)
   {
      $this->error_check($file['error'], $file['size']);

      $path = $this->getPathname($type);
      $filename = $this->findFilename($path, $file['name']);

      $uploadfile = $path . $filename;
      if(move_uploaded_file($file['tmp_name'], $uploadfile)){
         $sql = "INSERT INTO `files`.`fp_files` (`id` ,`filename` ,`size` ,`user` ,`date` ,`time` ,`public`) VALUES (
						NULL , '".$filename."', '".filesize($uploadfile)."', '".$this->user->getUserId()."', CURDATE(), CURTIME(), '".$type."')";
         $result = mysql_query($sql, $GLOBALS['db']);
         $this->user->reloadSpace();
         return $filename;
      }else{
         throw new Exception("Something went wrong with your upload.");
      }

   }

   private function error_check($error, $size)
   {
      if($size > $this->user->getRemainingSpace())
         throw new Exception("Filesize exceeded the allowed filesize");

      switch ($error) {
         case UPLOAD_ERR_OK:
            break;
         case UPLOAD_ERR_INI_SIZE:
            throw new Exception("The uploaded file exceeds the upload_max_filesize directive (".ini_get("upload_max_filesize").") in php.ini.");
            break;
         case UPLOAD_ERR_FORM_SIZE:
            throw new Exception("Filesize exceeded the allowed filesize");
            break;
         case UPLOAD_ERR_PARTIAL:
            throw new Exception("The uploaded file was only partially uploaded.");
            break;
         case UPLOAD_ERR_NO_FILE:
            throw new Exception("No file was uploaded.");
            break;
         case UPLOAD_ERR_NO_TMP_DIR:
            throw new Exception("Missing a temporary folder.");
            break;
         case UPLOAD_ERR_CANT_WRITE:
            throw new Exception("Failed to write file to disk");
            break;
         default:
            throw new Exception("Unknown Upload Error");
        }
   }


   public function checkList(&$listdata)
   {
      $sql = "SELECT `id`, `filename`, `public` FROM `fp_files` WHERE `user`='".$this->user->getUserId()."' AND (`id`='0'";
      for($i=0;isset($listdata[$i]);$i++)
         if(is_numeric($listdata[$i][0]))
            $sql .= " OR `id`='".$listdata[$i][0]."'";
      $sql .= ")";


      $result = mysql_query($sql, $GLOBALS['db']);

      for($i=0;$i<mysql_num_rows($result);$i++){
         $array = mysql_fetch_array($result);

         if($listdata['id_'.$array['id']][3] == 1){
            $this->del($array['id'], $array['filename'], $array['public']);
            continue;
         }

         if($array['filename'] != $listdata['id_'.$array['id']][1] || $array['public'] != $listdata['id_'.$array['id']][2]){
            $this->change($array['id'], $array['filename'], $array['public'], $listdata['id_'.$array['id']][1], $listdata['id_'.$array['id']][2]);
         }
     }

   }



   private function del($id, $file, $type)
   {
      $sql = "DELETE FROM `fp_files` WHERE `id`='".$id."'";
      $result = mysql_query($sql, $GLOBALS['db']);

      $path = $this->getPathname($type);
      if(!@unlink($path.$file))
         throw new Exception("File could not be removed. ".$path.$file);

      $this->user->reloadSpace();
   }


   private function change($id, $oldname, $oldtype, $nwname, $nwtype)
   {
      $nwpath = $this->getPathname($nwtype);
      $oldpath = $this->getPathname($oldtype);

      $newfilename = $this->findFilename($nwpath, $nwname);

      $sql = "UPDATE `fp_files` SET `filename` = '".$newfilename."', `public` = '".$nwtype."' WHERE `id`='".$id."' LIMIT 1" ;
      $result = mysql_query($sql, $GLOBALS['db']);

      if(!@rename($oldpath.$oldname, $nwpath.$newfilename))
         throw new Exception("File could not be moved. ".$oldpath.$oldname." -> ".$nwpath.$newfilename);
   }

   private function getPathname($type)
   {
      return ($type == 0) ? FILEROOT.USERFILES.$this->user->getUserId()."/" : FILEROOT.FILEPOOL;
   }


   private function findFilename($path, $filename)
   {

      $extension = substr(strrchr($filename, "."), 1);
      $file = basename($filename, ".".$extension);
      $file = $this->urlencode_nice($file);
        
      $i=0;
      $finalfile = $file.".".$extension;
      while (file_exists($path.$finalfile)){
         $finalfile = $file."_".$i.".".$extension;
         $i++;
      }
      return $finalfile;
   }

   private function urlencode_nice($name)
   {
      $end = substr($name, 0, MAX_NAME_LENGTH);
      $end = rawurlencode($end);

      $urlencodes_german = array("%C3%A4%", "%C3%BC%", "%C3%B6", "%C3%9C", "%C3%96%", "%C3%84", "%C3%9F", "%20");
      $replaces_german  = array("ae", "ue", "oe", "Ue", "Oe", "Ae", "ss", "_");

      $end = str_replace($urlencodes_german, $replaces_german, $end);
      return $end;
   }

}



class FileList
{

   private $page;
   private $user;
   private $showtype;
   const rpp = 10;

   public function __construct(&$user, $showtype, $page)
   {
      $this->page = $page;
      $this->user = $user;
      $this->showtype = $showtype;
   }

   private function createSQLResult()
   {
      if($this->isPrivate())
         $sql = "SELECT `id`, `filename`, `size`, `date`, `time`, `public` FROM `fp_files` WHERE `user`='".$this->user->getUserId()."' ORDER BY `date` DESC, `time` DESC LIMIT ".(($this->page-1)*RESULTS_PER_PAGE).",".RESULTS_PER_PAGE."";
      else
         $sql = "SELECT `fp_files`.`filename`, `fp_files`.`size`, `fp_files`.`date`, `fp_files`.`time`, `fp_users`.`usrname` FROM `fp_files` LEFT JOIN `fp_users` ON `fp_users`.`id` = `fp_files`.`user` WHERE `public`='1' ORDER BY `date` DESC, `time` DESC LIMIT ".(($this->page-1)*RESULTS_PER_PAGE).",".RESULTS_PER_PAGE."";

      $result = mysql_query($sql, $GLOBALS['db']);
      return $result;

   }

   private function writePageList()
   {
      if($this->isPrivate())
         $sql = "SELECT COUNT(`id`) FROM `fp_files` WHERE `user`='".$this->user->getUserId()."'";
      else
         $sql = "SELECT COUNT(`id`) FROM `fp_files` WHERE `public`='1'";

      $result = mysql_query($sql, $GLOBALS['db']);
      $maxpages =  ceil(mysql_result($result, 0, 0)/RESULTS_PER_PAGE);
  
      echo "<div id=\"fileViewPages\">";

      if($this->page < 1 || $this->page > $maxpages)
         $this->page = 1;
      else
         if($this->page != 1)
            echo "<a href=\"index.php?kat=".$this->showtype."&amp;page=".($this->page-1)."\">&lt;&lt;</a>&nbsp;";

      for($i=1;$i<=$maxpages;$i++)
          if($i == $this->page)
             echo "&nbsp;<span id=\"fileViewCurrentPage\">".$i."</span>&nbsp;";
          else
             echo "&nbsp;<a href=\"index.php?kat=".$this->showtype."&amp;page=".$i."\">".$i."</a>&nbsp;";

      if($maxpages != $this->page)
         echo "&nbsp;<a href=\"index.php?kat=".$this->showtype."&amp;page=".($this->page+1)."\">&gt;&gt;</a>";
      echo "</div>";
   }


   public function writeFileList()
   {
      $this->startTable();
      if($this->isPrivate()) 
         $this->writePrivateFiles();
			else
         $this->writePublicFiles();
      $this->endTable();
   }
   
   private function writePublicFiles()
   {
      $result = $this->createSQLResult();
      for($i=0;$i<mysql_num_rows($result);$i++){
         $array = mysql_fetch_array($result);
         echo "<tr>";
         echo "<td class=\"fileViewTD\"><a rel=\"external\" href=\"".HTTPROOT.FILEPOOL.rawurlencode($array['filename'])."\" class=\"filelink\">".$array['filename']."</a></td>";
         echo "<td class=\"fileViewTD\">".Handler::fileSize($array['size'])."</td>";
         echo "<td class=\"fileViewTD\">".$array['usrname']."</td>";
         echo "<td class=\"fileViewTD\">".date("d.m.y", strtotime($array['date']))."</td>";
         echo "<td class=\"fileViewTD\">".$array['time']."</td>";
         echo "</tr>";
      }
   }

   private function writePrivateFiles()
   {
      $result = $this->createSQLResult();
      for($i=0;$i<mysql_num_rows($result);$i++){
         $array = mysql_fetch_array($result);
         echo "<tr><td class=\"fileViewTD\">";
         echo "<input type=\"text\" name=\"filename_".$i."\" value=\"".$array['filename']."\" />";
         echo "</td><td class=\"fileViewTD\">";
         echo "<a rel=\"external\" href=\"".HTTPROOT;
         echo ($array['public'] == "1") ? FILEPOOL : USERFILES.$this->user->getUserId()."/";
         echo rawurlencode($array['filename'])."\" class=\"filelink\">Here</a>";
         echo "</td>";
         echo "<td class=\"fileViewTD\">".Handler::fileSize($array['size'])."</td>";
         echo "<td class=\"fileViewTD\">".date("d.m.y", strtotime($array['date']))."</td>";
         echo "<td class=\"fileViewTD\">".$array['time']."</td>";
         echo "<td class=\"fileViewTD\">";
         echo "<input type=\"checkbox\" name=\"public_".$i."\" value=\"1\"";
         if($array['public'] == 1) echo " checked=\"checked\"";
				 echo " /></td><td class=\"fileViewTD\">";
         echo "<input type=\"checkbox\" name=\"del_".$i."\" value=\"1\" />";
         echo "<input name=\"hidden_id_".$i."\" type=\"hidden\" value=\"".$array['id']."\" />";
         echo "</td></tr>";
      }
					

   }


   private function startTable()
   {
      if($this->isPrivate()){
         echo "<div class=\"subheadline\">My files:</div>";
         echo "<form method=\"post\" action=\"index.php?kat=ownfiles&amp;page=".$this->page."\">";
      }else{
         echo "<div class=\"subheadline\">Current public files:</div>";
      }

      $this->writePageList();
      echo "<table id=\"fileviewtable\">";

      if($this->isPrivate()){
         echo "<tr>";
         $this->writeHeadline(array("Name", "Download", "Size", "Date(DD.MM.YY)", "Time", "Public", "Delete"));
         echo "</tr>";
      }else{
         echo "<tr>";
         $this->writeHeadline(array("Name", "Size", "User", "Date(DD.MM.YY)", "Time"));
         echo "</tr>";
 
      }
   }

   private function writeHeadline($array)
   {
      foreach ($array as $val)
         echo "<th class=\"fileViewTH\">".$val."</th>";
   }


   private function endTable()
   {
      if($this->isPrivate()){
        echo "<tr><td colspan=\"7\" id=\"fileListChangeTD\">";
        echo "<input type=\"submit\" name=\"kat\" value=\"Change\" /></td></tr>";
      }

      echo "</table>";
      if($this->isPrivate())
      echo "</form>";
   }

   private function isPrivate() 
   { if($this->showtype == "ownfiles") return true; else return false;}
}





class Handler
{

   public static function doSomething() {

      switch($_POST['kat'])
      {
         case "Login":
            self::checkNeededVars($_POST, array("loginname", "loginpswd"));
            $GLOBALS['user']->login($_POST['loginname'], $_POST['loginpswd']);
            break;
         case "Change":
            $i=0;$infos = array();
            $file = new FileHandler($GLOBALS['user']);
            self::checkNeededVars($_POST, array("hidden_id_".$i, "filename_".$i));
            while(isset($_POST['filename_'.$i])){
               $_POST['public_'.$i] = (isset($_POST['public_'.$i])) ? 1 : 0;
               $_POST['del_'.$i] = (isset($_POST['del_'.$i])) ? 1 : 0;

               $infos[$i] = array($_POST['hidden_id_'.$i], $_POST['filename_'.$i], $_POST['public_'.$i], $_POST['del_'.$i]);
               $infos['id_'.$_POST['hidden_id_'.$i]] = &$infos[$i];
               $i++;
            }
            $file->checkList($infos);
            break;
         case "Submit":
            self::checkNeededVars($_POST, array("name", "pswd1", "pswd2"));
            if($GLOBALS['user']->getUserName() != $_POST['name'])
               $GLOBALS['user']->changeMyName($_POST['name']);

            if(!empty($_POST['pswd1']) && !empty($_POST['pswd2']))
               $GLOBALS['user']->changePassword($_POST['pswd1'], $_POST['pswd2']);

            if(isset($_POST['maxspace']))
               $GLOBALS['user']->setMaxSpace($_POST['maxspace']);

            break;
         case "Upload":
            self::checkNeededVars($_FILES, array("loadfile"));
            $type = (isset($_POST['public'])) ? 1 : 0;
            $file = new FileHandler($GLOBALS['user']);
            $file->upload($_FILES['loadfile'], $type);
            break;
         case "Delete":
            self::checkNeededVars($_POST, array("id"));
            UserAdmin::delUser($_POST['id']);
            break;
         case "Add":
            self::checkNeededVars($_POST, array("name", "size"));
            UserAdmin::addUser($_POST['name'], $_POST['size']);
            break;
      }
   }

   public static function checkNeededVars($array, $needed)
   {
       foreach ($needed as $key)
          if(!isset($array[$key]))
             throw new Exception("Not all needed arguments given.".$key." missing");
   }

   public static function fileSize($size){
      if(empty($size))
         return "0 Byte";

      if($size<1000){
         $einheit = " Byte";
         $size_end = $size;
      }elseif($size>=1000&&$size<1000000){
         $einheit = " KB";
         $size_end = $size/1000;
      }elseif($size>=1000000){
         $einheit = " MB";
         $size_end = $size/1000000;
      }
      return $size_end.$einheit;
   }

   public static function spacePercentageBar($max, $used)
   {
      $percent = 100-round(($used * 100)/$max);


   
      $return_text = "<table id=\"percentageBarTable\">";


      if($percent == 100) $color = "#0DF900";
      elseif($percent >= 90 && $percent < 100)$color = "#9AFE2C";
      elseif($percent >= 75 && $percent < 90) $color = "#B7FF0F";
      elseif($percent >= 50 && $percent < 75) $color = "#FFFF00";
      elseif($percent >= 25 && $percent < 50) $color = "#FFCC00";
      elseif($percent >= 10 && $percent < 25) $color = "#FF6600";
      elseif($percent > 0 && $percent < 10) $color = "#FF0000";
      elseif($percent == 0) $color = "#FF0000";



      $return_text .= "<tr><td style=\"background-color: ".$color."; width: ".($percent*2)."px\">&nbsp;</td>";
      $return_text .= "<td style=\"width: ".((100-$percent)*2)."px\">&nbsp;</td></tr>";

      $return_text .= "</table>";
      $return_text .= $percent."% from ".self::fileSize($max);


      return $return_text;
   }

}


class Menu
{
   private $user;
   private $menu;

   public function __construct(&$user)
	{
      $this->user = $user;
   }

   public function writeMenu()
   {
      $this->writeTH("Public Area");
      if($this->user->isLoggedIn() == false){
         $this->writeTD("login", "Login");
         $this->writeTD("public", "Public Area");
      }else{
         $this->writeTD("logout", "Logout");
         $this->writeTD("public", "Public Area");
         $this->writeTH("User Area");
         $this->writeTD("profile", "Manage your profile");
         $this->writeTD("ownfiles", "Manage your files");
         $this->writeTD("upload", "Upload files");
      }

      if($this->user->isAdmin() == true){
         $this->writeTH("Admin Area");
         $this->writeTD("useradm", "User management");
      }

   }

   private function writeTD($link, $name)
   {
       echo "<tr class=\"menuTR\"><td class=\"menuTD\"><a href=\"index.php?kat=".$link."\">".$name."</a></td></tr>";
   }


   private function writeTH($name)
   {
       echo "<tr class=\"menuTR\"><th class=\"menuTH\">".$name."</th></tr>";
   }
}



class User {

   private $userdata;
   private $admin = false;
   private $loggedin = false;
   private $usedsize = false;
   public function __construct()
   {

   }

   public function login($username, $pwd)
   {
      $sql = "SELECT `isadmin`, `id` FROM `fp_users` WHERE `usrname`='".$username."' AND `passwd`=MD5('".$pwd."')";
      $result = mysql_query($sql, $GLOBALS['db']);
      if(mysql_num_rows($result) != 1){
         throw new Exception("User not found / Password wrong.");
      }else{
         $this->admin = mysql_result($result, 0, 0);
         $this->loggedin = true;
         $this->loadUser(mysql_result($result, 0, 1));
      }
   }

   public function logout() {
      $this->loggedin = false;
      $this->admin = false;
      $this->usedsize = false;
      unset($this->userdata);
   }

   public function switchUser($userid)
   {
      if($this->admin == true && $userid != $this->userdata['id'])
         $this->loadUser($userid);
      else
         throw new Exception("You're not allowed to switch to another user or the operation is useless.");
   }

   private function loadUser($userid)
   {
      $sql = "(SELECT `id`, `usrname`, `passwd`, `maxspace` FROM `fp_users` WHERE `id` = '".$userid."') UNION ( SELECT SUM( `size` ) AS `id`, NULL as `usrname`,  NULL as `passwd`, NULL as `maxspace` FROM `fp_files` WHERE `user` = '".$userid."' )";
      $result = mysql_query($sql, $GLOBALS['db']);
      $array = mysql_fetch_array($result, MYSQL_ASSOC);
      $this->userdata = $array;
      $this->usedsize = mysql_result($result, 1, "id" );
   }

   public function reloadSpace() 
   {
      $sql = "SELECT SUM( `size` ) FROM `fp_files` WHERE `user` = '".$this->userdata['id']."'";
      $this->usedsize = mysql_result(mysql_query($sql, $GLOBALS['db']), 0, 0 );

   }

   public function checkKat( &$get, &$post ) 
   {
      if($this->loggedin == false){
         if($get != "login" && $get != "public"){
            $get = "public";
         }
         if($post != "Login")
            $post = "";
      }else{
         if($get == "useradm" && $this->admin == false)
            $get = "public";
         if(($post == "Delete" || $post == "Add") && $this->admin == false)
            $post = "";
      }
   }

   public function changeMyName($nwname)
   {
      if(empty($nwname))
         throw new Exception("No Username given.");

      $sql = "UPDATE `fp_users` SET `usrname`='".$nwname."' WHERE `id`='".$this->userdata['id']."'";
      if(!mysql_query($sql, $GLOBALS['db']))
         throw new Exception("Error in username change. Maybe username exists already?");
      else
         $this->userdata['usrname'] = $nwname;

   }

   public function changePassword($nwpasswd1, $nwpasswd2)
   {
      if($nwpasswd1 != $nwpasswd2)
         throw new Exception("Passwords don't match.");

      $sql = "UPDATE `fp_users` SET `passwd`=MD5('".$nwpasswd1."') WHERE `id`='".$this->userdata['id']."'";
      if(!mysql_query($sql, $GLOBALS['db']))
         throw new Exception("Error in password change.");

   }

   public function setMaxSpace($nwspace)
   {
      if(!$this->admin || !is_numeric($nwspace))
         throw new Exception("Hacking attempt, stop right here you idiot");

      $sql = "UPDATE `fp_users` SET `maxspace`='".$nwspace."' WHERE `id`='".$this->userdata['id']."'";
      if(!mysql_query($sql, $GLOBALS['db']))
         throw new Exception("Error in maxspace change."); 

      $this->userdata['maxspace'] = $nwspace;
   }


   public function isAdmin() { return $this->admin; }
   public function getMaxSpace() { return $this->userdata['maxspace']; }
   public function getUsedSpace() { return $this->usedsize; }
   public function getRemainingSpace(){ return ($this->userdata['maxspace']-$this->usedsize);  }
   public function isLoggedIn(){ return $this->loggedin; }
   public function getUserId(){ return $this->userdata['id']; }
   public function getUserName(){ return $this->userdata['usrname']; }
}


class UserAdmin
{


   public static function writeList()
   {
      $sql = "SELECT `fp_users`.`id`, `maxspace`, `usrname`, `used` FROM `fp_users` LEFT JOIN (SELECT `user`, SUM(`size`) AS `used` FROM `fp_files` GROUP BY `user`) AS `tmp1` ON `fp_users`.`id` = `user`";
      $result = mysql_query($sql, $GLOBALS['db']);

      echo "<table id=\"usrad_table\">";

      echo "<tr><th class=\"usradViewTH\">Name</th><th class=\"usradViewTH\">Used</th><th class=\"usradViewTH\">Max</th><th class=\"usradViewTH\">Delete</th></tr>";

      for($i=0;$i<mysql_num_rows($result);$i++){
         $array = mysql_fetch_array($result);
         echo "<tr><td class=\"usradViewTD\">";
         if($array['id'] != $GLOBALS['user']->getUserId())
            echo "<a href=\"index.php?kat=ownfiles&amp;usrid=".$array['id']."\">".$array['usrname']."</a>";
         else
            echo $array['usrname'];
         echo "</td>";

         echo "<td class=\"usradViewTD\">".Handler::fileSize($array['used'])."</td>";
         echo "<td class=\"usradViewTD\">".Handler::fileSize($array['maxspace'])."</td>";
         echo "<td class=\"usradViewTD\"><form method=\"post\" action=\"index.php?kat=useradm\"><div>";
         echo "<input type=\"hidden\" name=\"id\" value=\"".$array['id']."\" />";
         echo "<input type=\"submit\" name=\"kat\" value=\"Delete\" />";
         echo "</div></form></td></tr>";
      }

      echo "</table>";

      echo "<form method=\"post\" action=\"index.php?kat=useradm\">";
      echo "<p>Name:<input type=\"text\" name=\"name\" size=\"10\" /><br />";
      echo "Space:<input type=\"text\" name=\"size\" size=\"5\" /></p>";
      echo "<p><input type=\"submit\" value=\"Add\" name=\"kat\" /></p>";
      echo "</form>";




   }



   public static function addUser($name, $size)
   { 
      if(!$GLOBALS['user']->isAdmin())
         throw new Exception("Dude, stop pocking around. Someone might get seriously pissed off!");

      if(empty($name))
         throw new Exception("No Username given.");
      if(empty($size))
         throw new Exception("No Space given.");

      $sql = "INSERT INTO `fp_users` (`id`,`usrname`,`passwd`,`maxspace`,`isadmin`) VALUES (NULL,'".$name."','', '".$size."', '0')";
      $result = mysql_query($sql, $GLOBALS['db']);
      if(!$result)
         throw new Exception("User couldn't be added. Maybe username is used already?");

      $id = mysql_insert_id();
      mkdir(FILEROOT.USERFILES.$id);
   }


   public static function delUser($id)
   {
      if(!$GLOBALS['user']->isAdmin())
         throw new Exception("fu, get outta here!");

      $sql1 = "DELETE FROM `fp_users` WHERE `id`='".$id."'";
      $sql2 = "DELETE FROM `fp_files` WHERE `user`='".$id."'";
      $result = mysql_query($sql1, $GLOBALS['db']);
      $result2 = mysql_query($sql2, $GLOBALS['db']);

      exec("rm -Rf ".FILEROOT.USERFILES.$id."/");
   }
}



?>
