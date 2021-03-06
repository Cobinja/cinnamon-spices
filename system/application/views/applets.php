
<div id="main">
        	<div id="post-content" class="clearfix">
				        		<h1 class="page-title">Applets</h1>	
                                
    <p>To install an applet: Download it and decompress it in ~/.local/share/cinnamon/applets.</p>
                                
<?php if($latest->num_rows > 0) {
    alternator();
    ?>
<div class="greydiv">    
    <h4 align="center">Latest</h4>
    <table border="0" cellspacing="10" cellpadding="0">    
    <tbody><tr>        
        <?php foreach($latest->result() as $applet):                        
            $array = preg_split("/,/", timespan($applet->last_edited, time()));        
            $time = strtolower($array[0])." ago";          
            $score = "Score: ".$applet->score;            
        ?>
            <td style="vertical-align: bottom;" align="center">
                <center>
                    <div class="wp-caption alignnone" style="width: 120px; border: 1px solid #a5a5a5;">                        
                    <?=anchor("applets/view/$applet->id", "<img src='$applet->icon' width='48'/>")?>
                    <p class="wp-caption-text"><?=anchor("applets/view/$applet->id", "$applet->name")?><br/><?=$score?><br/><?=$time?></p>
                    </div>
                </center></td>
            <?= alternator('', '', '', '', '</tr><tr>') ?>
        <?php endforeach;?>
        </tr>
    </tbody>
    </table>
</div>
<?php } ?>

<?php if($popular->num_rows > 0) {
    alternator();
    ?>
<div class="greydiv">    
    <h4 align="center">Most popular</h4>
    <table border="0" cellspacing="10" cellpadding="0">    
    <tbody><tr>        
        <?php foreach($popular->result() as $applet):                        
            $array = preg_split("/,/", timespan($applet->last_edited, time()));        
            $time = strtolower($array[0])." ago";          
            $score = "Score: ".$applet->score;            
        ?>
            <td style="vertical-align: bottom;" align="center">
                <center>
                    <div class="wp-caption alignnone" style="width: 120px; border: 1px solid #a5a5a5;">                        
                    <?=anchor("applets/view/$applet->id", "<img src='$applet->icon' width='48'/>")?>
                    <p class="wp-caption-text"><?=anchor("applets/view/$applet->id", "$applet->name")?><br/><?=$score?><br/><?=$time?></p>
                    </div>
                </center></td>
            <?= alternator('', '', '', '', '</tr><tr>') ?>
        <?php endforeach;?>
        </tr>
    </tbody>
    </table>
</div>
<?php } ?>								
                                
      
                
</div>
<!-- END post-content -->
