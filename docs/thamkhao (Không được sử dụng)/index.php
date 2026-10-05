<?php	
$dir = dirname ( __FILE__ );
$files = scandir ( $dir, 2 );
$fileArray = array ();
$dirArray = array ();
$phpFileArray = array ();
foreach ( $files as $file ) {
	if (is_file ( $file )) {
		$ex = explode ( '.', $file );
		$ex = $ex [count ( $ex ) - 1];
		if ("php" == strtolower ( $ex )) {
			array_push ( $phpFileArray, $file );
		} else {
			array_push ( $fileArray, $file );
		}
	} else {
		array_push ( $dirArray, $file );
	}
}
array_pop ( $dirArray );
array_pop ( $dirArray );

?>
 <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>Dinh Quyet DOWNLOAD 4ALL</title>
		<link href="http://acad.vn/templates/dinhquyet/favicon.ico" rel="shortcut icon" type="image/x-icon" />
        <link type="text/css" href="http://acad.vn/down/up/css/css/start/jquery-ui-1.7.1.custom.css" rel="stylesheet" />
        <script type="text/javascript" src="http://acad.vn/down/up/css/js/jquery-1.3.2.min.js">
        </script>
        <script type="text/javascript" src="http://acad.vn/down/up/css/js/jquery-ui-1.7.1.custom.min.js">
        </script>
        <style type="text/css">            
            body {
                font: 62.5% "Trebuchet MS", sans-serif;
                margin-top: 50px;
                margin-right: 50px;
                margin-bottom: 10px;
                margin-left: 50px;
            }
            
            a:link {
                text-decoration: none;
                color: #1096bf;
                font-weight: bold;
                font-size: 12px;
            }
            
            a:visited {
                text-decoration: none;
                color: #1096bf;
                font-weight: bold;
                font-size: 12px;
            }
            
            a:hover {
                border-right: 1px solid #D4D4D4;
                border-bottom: 1px solid #D4D4D4;
                list-style-position: inside;
                text-decoration: none;
                color: #CC3;
                border-top-width: 1px;
                border-left-width: 1px;
                border-top-color: #D4D4D4;
                border-left-color: #D4D4D4;
                background-image: url(005.gif);
                cursor: crosshair;
                filter: shadow( color =
            
            red, strength = 2, direction = 135);
            }
            
            a:active {
                color: #FF0000;
            } .demoHeaders {
                margin-top: 2em;
            } .dialog_link {
                padding: .4em 1em .4em 20px;
                text-decoration: none;
                position: relative;
            } .dialog_link span.ui-icon {
                margin: 0 5px 0 0;
                position: absolute;
                left: .2em;
                top: 50%;
                margin-top: -8px;
            } .link {
                padding: .4em 1em .4em 20px;
                text-decoration: none;
                position: relative;
            } .link span.ui-icon {
                margin: 0 5px 0 0;
                position: absolute;
                left: .2em;
                top: 50%;
                margin-top: -8px;
            } #container {
                height: 620px;
            }
            
            img {
                margin-right: 5px;
            } #footer-phplemon {
                text-align: center;
                clear: both;
            }
        </style>
        <script type="text/javascript">
            $(document).ready(function(){            
                //-------------------------->                                                                                                
                $('#directories-header,#php-files-header,#other-files-header,.dialog_link').hover(function(){
                    $(this).addClass('ui-state-hover');
                }, function(){
                    $(this).removeClass('ui-state-hover');
                });
                //<--------------------------
            });
        </script>
    </head>
    <body>
	
	
	<form action="http://www.google.com.vn" id="cse-search-box">
  <div>
    <input type="hidden" name="cx" value="partner-pub-9329673164915043:7125037212" />
    <input type="hidden" name="ie" value="UTF-8" />
    <input type="text" name="q" size="80" />
    <input type="submit" name="sa" value="Search" />
  </div>
</form>

<script type="text/javascript" src="http://www.google.com.vn/coop/cse/brand?form=cse-search-box&amp;lang=vi"></script>
        <div id="container">
            <div class="ui-tabs ui-widget ui-corner-all">
                <div class="ui-tabs-panel ui-widget-content ui-corner-bottom">
Đinh Văn Quyết
                </div>
            </div>
            <div class="ui-tabs ui-widget ui-widget-content ui-corner-all" id="directories">
                <div class="ui-state-default ui-corner-all link" id="directories-header">
                    <span class="ui-icon ui-icon-folder-open"></span>Thư mục
                </div>
                <div class="ui-tabs-panel ui-widget-content ui-corner-bottom">
                    <?php	 	
						foreach ( $dirArray as $file ) {
							echo "<img src=\"http://acad.vn/down/up/css/phplemon/dir.png\" /><a href=\"$file\">" . $file . "</a><br />";
						}
                    ?>
                </div>
            </div>
            <div class="ui-tabs ui-widget ui-widget-content ui-corner-all" id="php-files">
                <div class="ui-state-default ui-corner-all link" id="php-files-header">
                    <span class="ui-icon ui-icon-script"></span>PHP files
                </div>
                <div class="ui-tabs-panel ui-widget-content ui-corner-bottom" id="test-c">
                    <?php	 
						foreach ( $phpFileArray as $file ) {
							echo "<img src=\"http://acad.vn/down/up/css/phplemon/php.png\" /><a href=\"$file\">" . $file . "</a><br />";
						}
					?>
                </div>
            </div>
            <div class="ui-tabs ui-widget ui-widget-content ui-corner-all" id="other-files">
                <div class="ui-state-default ui-corner-all link" id="other-files-header">
                    <span class="ui-icon ui-icon-document"></span>CÃ¡c file khÃ¡c
                </div>
                <div class="ui-tabs-panel ui-widget-content ui-corner-bottom" id="test-c">
                    <?php	 	
						foreach ( $fileArray as $file ) {
							echo "<img src=\"http://acad.vn/down/up/css/phplemon/file.png\" /><a href=\"$file\">" . $file . "</a><br />";
						}
					?>
                </div>
            </div>
            <div id="footer-phplemon">
				<br />
                <a href="http://acad.vn/">Trung tÃ¢m dá»¯ liá»‡u ACAD.VN By Quyet_bkh</a>
            </div>
        </div>
    </body>
</html>