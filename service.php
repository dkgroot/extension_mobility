<?php 
  header("Content-type: text/xml;charset=utf-8");
  include "controller.php";

  $page = new cisco\service();
  if (empty($page->class_error)) {
    $page->process_request();
    /*
    print("<br> Request:<br><pre>");
    print_r($page);
    print("</pre>");
    */
    $display_page = $page->ServiceShowPage();
    
    foreach($display_page as $key => $page) {
         echo $page['content'];
    }
  } else {
    print_r("<br> Request:<br><pre>");
    print_r("<br>END");
    print("</pre>");
}
?>
