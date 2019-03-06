<?php
$URL_srv = $this->xml_url;
$PAGE_Prefix = $this->page_title;
?>
<CiscoIPPhoneMenu>
  <Title><?php echo $PAGE_Prefix;?> Directory</Title>
  <MenuItem>
      <Name>Login</Name>
      <URL><?php echo $URL_srv;?>&amp;action=loginform</URL>
  </MenuItem>
  <?php if ($this->dev_login !== false) {
    echo '<MenuItem><Name>Logout</Name><URL>'.$URL_srv.'&amp;action=logout</URL></MenuItem>';
   }?>
  <SoftKeyItem>
      <Name>Exit</Name>
      <URL>SoftKey:Exit</URL>
      <Position>4</Position>
  </SoftKeyItem>
  <SoftKeyItem>
      <Name>Back</Name>
      <URL>SoftKey:Select</URL>
      <Position>2</Position>
  </SoftKeyItem>
  <SoftKeyItem>
   <Name>Select</Name>
   <URL>SoftKey:Select</URL>
   <Position>1</Position>
  </SoftKeyItem>
  <SoftKeyItem>
      <Name>Help</Name>
      <URL>Init:Directories</URL>
      <Position>4</Position>
  </SoftKeyItem>
</CiscoIPPhoneMenu>
