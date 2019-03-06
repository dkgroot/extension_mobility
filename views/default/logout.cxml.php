<?php
$URL_srv = $this->xml_url;
$PAGE_Prefix = $this->page_title;
?>
<CiscoIPPhoneInput>
  <Title><?php echo $PAGE_Prefix;?> Login</Title>
  <Prompt>Please Login</Prompt>
  <URL><?php echo $URL_srv;?>&amp;deviceid=#DEVICENAME#&amp;action=login&amp;sessionid=$this->sessionid</URL>
  <InputItem>
    <DisplayName>Name</DisplayName>
    <QueryStringParam>userid</QueryStringParam>
    <InputFlags>N</InputFlags>
  </InputItem>
  <InputItem>
    <DisplayName>Pin</DisplayName>
    <QueryStringParam>pin</QueryStringParam>
    <InputFlags>NP</InputFlags>
  </InputItem>
</CiscoIPPhoneInput>
