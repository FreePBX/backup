$(document).ready(() => {

  $("#backupUpload").click( event => {
    event.preventDefault();
    let file = $("#filetorestore")[0].files[0];
    var formData = new FormData();
    formData.append('filetorestore', file);
    $.ajax({
      url: `${ajaxurl}?module=backup&command=uploadrestore`,
      type: 'POST',
      data: formData,
      processData: false,  // tell jQuery not to process the data
      contentType: false  // tell jQuery not to set contentType
    })
    .then(data => {
      if(data.status == true){
        let url = `${window.location.href}&view=processrestore&id=${data.id}`;
        console.log(url);
        //window.location = url;
      }else{
        fpbxToast(data.message, 'error');
      }
    })
    .fail(err =>{
      fpbxToast("Unable to upload File");
      console.log(err);
      return false;
    });
    return false;
  });
<<<<<<< HEAD

=======
	const inputElement = document.querySelector('input[type="file"]');
  const pond = FilePond.create( inputElement );
  pond.registerPlugin('filepond-plugin-file-validate-type');
  pond.setOptions({
    server: ajaxurl+'?module=backup&command=uploadrestore',
    instantUpload: true,
    acceptedFileTypes: ['application/x-gzip'],
    labelFileTypeNotAllowed: _('File of invalid type'),
    abelIdle: _("Drag & Drop your files or <span class=\"filepond--label - action\"> Browse </span>"),
    labelFileWaitingForSize: _("Waiting for size"),
    labelFileSizeNotAvailable: _("Size not available"),
    labelFileLoading: _("Loading"),
    labelFileLoadError: _("Error during load"),
    labelFileProcessing: _("Uploading"),
    labelFileProcessingComplete: _("Upload complete"),
    labelFileProcessingAborted: _("Upload cancelled"),
    labelFileProcessingError: _("Error during upload"),
    labelTapToCancel: _("tap to cancel"),
    labelTapToRetry: _("tap to retry"),
    labelTapToUndo: _("tap to undo"),
    labelButtonRemoveItem: _("Remove"),
    labelButtonAbortItemLoad: _("Abort"),
    labelButtonRetryItemLoad: _("Retry"),
    labelButtonAbortItemProcessing: _("Cancel"),
    labelButtonUndoItemProcessing: _("Undo"),
    labelButtonRetryItemProcessing: _("Retry"),
    labelButtonProcessItem: 	_('Upload')
  });
>>>>>>> development/15.0
});//end document ready
