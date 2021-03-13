<?php
	
session_start();

include_once("config.php");
include_once("connect.php");
include_once("fileproject.class.php");

if(isset($_SESSION['user']))
   $user = unserialize($_SESSION['user']);
else
   $user = new User();

$user->checkKat($_GET['kat'], $_POST['kat']);

if(isset($_GET['usrid'])) 
   try {
      $user->switchUser($_GET['usrid']);
   } catch (Exception $e) {
      $error = $e->getMessage();
   }
   

if(count($_POST) > 0){
   $error = "";
   try {
      Handler::doSomething();
   } catch (Exception $e) {
      $error = $e->getMessage();
   }
}

if($_GET['kat'] == "logout")
   $user->logout();

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
   <head>
      <title>The FileProject</title>
      <link rel="stylesheet" type="text/css" href="style.css" />
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <script type="text/javascript">
         /* <![CDATA[ */
         function externalLinks() {
            if (!document.getElementsByTagName) return;
            var anchors = document.getElementsByTagName("a");
            for (var i=0; i<anchors.length; i++) {
               var anchor = anchors[i];
               if (anchor.getAttribute("href") && anchor.getAttribute("rel") == "external")
                  anchor.target = "_blank";
            }
         }
         window.onload = externalLinks;
         /* ]]> */
      </script>
   </head>

   <body>

   <table id="layouttable">
      <tr>
         <td id="layoutHeadline" colspan="2">
            <div class="headline">The File-Project</div>
            Your serverbased Online-File-Sharing-System
         </td>
      </tr>
      <tr>
         <td id="layoutMenu">

            <table id="menuTable">
               <?php
               $menu = new Menu($user);
               $menu->writeMenu();
               $sql = "SELECT `fp_files`.`id`, `fp_files`.`filename`, `fp_files`.`date`, `fp_files`.`time`, `fp_users`.`usrname` FROM `fp_files` LEFT JOIN `fp_users` ON `fp_users`.`id` = `fp_files`.`user` WHERE `public`='1' ORDER BY `date` DESC, `time` DESC LIMIT 1";
               $result = mysql_query($sql, $GLOBALS['db']);
               $array = mysql_fetch_array($result);
               ?>
               <tr class="menuTR"><th class="menuTH">Information</th></tr>
               <tr class="menuTR">
                  <td class="menuTD">Latest file: 
                     <a href="<?php echo HTTPROOT.FILEPOOL.rawurlencode($array['filename']); ?>" class="filelink" rel="external"><?php echo $array['filename']; ?></a>
                  </td>
               </tr>
               <tr class="menuTR"><td class="menuTD">By: <?php echo $array['usrname']; ?></td></tr>
               <tr class="menuTR"><td class="menuTD">Date: <?php echo date("d.m.y", strtotime($array['date'])); ?></td></tr>
               <tr class="menuTR"><td id="menuLastTD">At: <?php echo $array['time']; ?></td></tr>
            </table>	
         </td>
         <td id="layoutContent"><?php
            if(!empty($error)) echo "<span id=\"errorParagraph\">".$error."</span>";

            switch($_GET['kat'])
            {
               case "login":
                  ?><div class="subheadline">Login</div>
                     <form method="post" action="index.php">
                        <p><input size="30" name="loginname" /><br />
                        <input size="30" type="password" name="loginpswd" /></p>
                        <p><input type="submit" value="Login" name="kat" /></p>
                     </form>
                  <?php
                  break;
               case "upload":
                  ?><div class="subheadline">File-Upload</div>
                  <form method="post" action="index.php?kat=upload" enctype="multipart/form-data">
                     <div id="uploadSpaceLeft">You have <?php echo Handler::fileSize($user->getRemainingSpace() ); ?> left for uploads</div>
                     <table id="uploadTable">
                        <tr><td colspan="2"><input name="loadfile" type="file" size="40" /></td></tr>
                        <tr><td id="uploadTablePublic"><input type="checkbox" name="public" value="1" /> Publish file</td>
                        <td  id="uploadTableButton"><input type="submit" value="Upload" name="kat" /></td></tr>
                     </table>
                  </form>
                  <?php
                  break;
               case "profile":
                  ?><div class="subheadline">My Profile</div>
                  <form method="post" action="index.php?kat=profile">
                  <div>Welcome <?php echo $user->getUserName(); ?>, here you can change your profile.<br /><br /></div>

                  <table id="profileTable">
                     <tr>
                        <td class="profileCaption">User ID</td>
                        <td class="profileTd"><?php echo $user->getUserId(); ?></td>
                     </tr>
                     <tr>
                        <td class="profileCaptionBottomLine" >Name</td>
                        <td class="profileBottomLine"><input type="text" name="name" size="20" maxlength="50" value="<?php echo $user->getUserName(); ?>" /></td>
                     </tr>
                     <tr>
                        <td class="profileCaption" >New</td>
                        <td class="profileTd"><input type="password" name="pswd1" size="20" maxlength="10" /></td>
                     </tr>
                     <tr>
                        <td class="profileCaptionBottomLine" >Password</td>
                        <td class="profileBottomLine"><input type="password" name="pswd2" size="20" maxlength="10" /></td>
                     </tr>
                     <tr>
                        <td class="profileCaption" >Used</td>
                        <td class="profileTd"><?php echo Handler::fileSize($user->getUsedSpace()); ?></td>
                     </tr>
                     <tr>
                        <?php if($user->isAdmin()){ ?>
                        <td class="profileCaption" >Maxspace</td>
                        <td class="profileTd"><input type="text" name="maxspace" size="20" value="<?php echo $user->getMaxSpace(); ?>" /></td>
                        <?php }else{ ?>
                        <td class="profileCaption" >Free</td>
                        <td class="profileTd"><?php echo Handler::spacePercentageBar($user->getMaxSpace(), $user->getUsedSpace()); ?></td>
                        <?php } ?>
                     </tr>
                     <tr>
                        <td class="profileCaption" >Change</td>
                        <td class="profileTd"><input type="submit" name="kat" value="Submit" /></td>
                     </tr>
                  </table>


                  </form>


                  <?php
                  break;
               case "useradm":
                  UserAdmin::writeList();
                  break;
               default:
                  if(!isset($_GET['page']))
                     $_GET['page'] = 1;
                  if($_GET['kat'] != "public" && $_GET['kat'] != "ownfiles")
                     $_GET['kat'] = "public";

                  $table = new FileList($user, $_GET['kat'], $_GET['page']);
                  $table->writeFileList();
                  break;

            }

            ?>
         </td>
      </tr>
      <tr>
         <td colspan="2" id="layoutFooter">
            <div id="footerLine">&nbsp;</div>
	          File-Project 2004 - Written by CSH and Lay-Z, revised by CSH (2008)<br />&copy; 2004 by CSH and Lay-Z <br/><img src="http://www.w3.org/Icons/valid-xhtml10-blue" alt="Valid XHTML 1.0 Strict" height="31" width="88" />
             <img style="border:0;width:88px;height:31px" src="http://www.w3.org/Icons/valid-css-blue" alt="Valid CSS!" />
         </td>
      </tr>
   </table>

   </body>
</html>
<?php 
   $_SESSION['user'] = serialize($user);
?>
