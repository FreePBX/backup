<div class="modal fade" id="runModal" tabindex="-1" aria-labelledby="runModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="runModalLabel">Modal title</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo _("Close")?></button>
      </div>
    </div>
  </div>
</div>
<style>
#runModal .modal-header {
	flex-direction: row;
	justify-content: space-between;
	align-items: center;
}
#runModal .modal-header .btn-close {
	display: inline-block;
	flex-shrink: 0;
	width: 12px;
	height: 12px;
	padding: 0;
	margin: 0;
	opacity: 0.5;
	background-size: 12px;
}
#runModal .modal-header .btn-close:disabled {
	opacity: 0.25;
}
#runModal .modal-body {
	overflow-y: auto;
}
#runModal .modal-body pre {
	white-space: pre-wrap;
	word-break: break-word;
	margin-bottom: 0;
	min-height: calc(100% - 2px);
}
</style>
