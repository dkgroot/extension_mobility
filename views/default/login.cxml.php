<?php
$URL_srv = $this->xml_url;
$PAGE_Prefix = $this->page_title;
?>
<CiscoIPPhoneInput>
  <Title><?php echo $PAGE_Prefix;?> Login</Title>
  <Prompt>Please Login</Prompt>
  <URL><?php echo $URL_srv;?></URL>
  <InputItem>
    <DisplayName>Name</DisplayName>
    <QueryStringParam>userid</QueryStringParam>
    <InputFlags>N</InputFlags>
    <?php if(isset($this->fields['prevUserId'])) print("<DefaultValue>" . $this->fields['prevUserId'] . "</DefaultValue>"); ?>
  </InputItem>
  <InputItem>
    <DisplayName>Pin</DisplayName>
    <QueryStringParam>pincode</QueryStringParam>
    <InputFlags>NP</InputFlags>
  </InputItem>
</CiscoIPPhoneInput>
