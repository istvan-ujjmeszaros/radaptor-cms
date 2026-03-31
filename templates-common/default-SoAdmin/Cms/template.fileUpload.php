<?php assert(isset($this) && $this instanceof Template); ?>
<div class="subheader"><h1><?= e($this->strings['widget.file_upload.name']) ?></h1><br class="cleaner"></div>
<?php //teszt: var_dump($this);?>
<h2><?= e($this->strings['cms.file_upload.destination_path']) ?>: <i><?= $this->props['destination_path']; ?></i></h2>

<applet id="jumpLoaderApplet" name="jumpLoaderApplet"
		code="jmaster.jumploader.app.JumpLoaderApplet.class"
		archive="<?= Config::PATH_CDN->value(); ?>_common/libraries/jumploader/jumploader_z.jar"
		width="100%"
		height="500"
		mayscript>
	<param name="ac_messagesZipUrl" value="<?= Config::PATH_CDN->value(); ?>_common/libraries/jumploader/messages_hu.zip"/>
	<param name="uc_imageEditorEnabled" value="true"/>
	<param name="uc_partitionLength" value="524288"/>
	<param name="vc_uploadViewStartUploadButtonText" value="<?= e($this->strings['common.upload']); ?>"/>
	<param name="vc_lookAndFeel" value="system"/>
	<param name="uc_directoriesEnabled" value="true"/>
	<param name="uc_maxFileLength" value="-1"/>
	<param name="uc_maxFiles" value="-1"/>
	<param name="uc_uploadQueueReorderingAllowed" value="true"/>
	<param name="uc_urlEncodeParameters" value="true"/>
	<param name="vc_uploadViewAutoscrollToUploadingFile" value="true"/>
	<param name="ac_fireUploaderFileStatusChanged" value="true"/>
	<param name="ac_fireAppletInitialized" value="true"/>
	<param name="uc_imageRotateEnabled" value="true"/>
	<param name="uc_uploadUrl" value="<?= ajax_url('jumploader.PartitionedUploadHandler') ?>&amp;ref_id=<?= $this->props['ref_id']; ?>"/>
	<param name="gc_loggingLevel" value="INFO"/>
</applet>
