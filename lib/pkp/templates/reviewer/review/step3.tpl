{**
 * templates/reviewer/review/step3.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Show the step 3 review page
 *}
<script type="text/javascript">
    $(function() {ldelim}
        // Attach the form handler.
        $('#reviewStep3Form').pkpHandler(
            '$.pkp.controllers.form.reviewer.ReviewerReviewStep3FormHandler'
        );
    {rdelim});
</script>

<form class="pkp_form" id="reviewStep3Form" method="post" action="{url op="saveStep" path=$submission->getId() step="3"}">
    <input type="hidden" name="isSave" />
    {csrf}
    {include file="controllers/notification/inPlaceNotification.tpl" notificationId="reviewStep3FormNotification"}

{fbvFormArea id="reviewStep3"}

    {capture assign="reviewFilesGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.ReviewerReviewFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$reviewAssignment->getStageId() reviewRoundId=$reviewRoundId reviewAssignmentId=$reviewAssignment->getId() escape=false}{/capture}
    {load_url_in_div id="reviewFilesStep3" url=$reviewFilesGridUrl}

    {if $viewGuidelinesAction}
        {fbvFormSection title="reviewer.submission.reviewerGuidelines"}
            <div id="viewGuidelines">
                {include file="linkAction/linkAction.tpl" action=$viewGuidelinesAction contextId="viewGuidelines"}
            </div>
        {/fbvFormSection}
    {/if}

    {* --- GEMINI AI REVIEWER ACTION BAR --- *}
{if $viewGuidelinesAction}
        {fbvFormSection title="reviewer.submission.reviewerGuidelines"}
            <div id="viewGuidelines">
                {include file="linkAction/linkAction.tpl" action=$viewGuidelinesAction contextId="viewGuidelines"}
            </div>
        {/fbvFormSection}
    {/if}

    {* --- GEMINI AI REVIEWER ACTION BAR --- *}
    {assign var="geminiPlugin" value=PluginRegistry::getPlugin('generic', 'geminireviewerplugin')}
    {if $geminiPlugin && $geminiPlugin->getEnabled()}
        {assign var="ctxId" value=$submission->getContextId()}
        {assign var="geminiShowReviewer" value=$geminiPlugin->getSetting($ctxId, 'showReviewer')}
        {if $geminiShowReviewer === null || $geminiShowReviewer}
            {capture assign=geminiReviewUrl}{url router=$smarty.const.ROUTE_PAGE page="geminiReviewerHandler" op="generateReview" submissionId=$submission->getId() role="reviewer"}{/capture}
            <div style="margin: 20px 0 10px 0; padding: 14px 18px; background: #f0fdf4; border: 1.5px solid #22c55e; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.03);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 24px;">🤖</span>
                    <div>
                        <strong style="font-size: 13px; color: #15803d; display: block;">Gemini AI Review Assistant</strong>
                        <span style="font-size: 12px; color: #4b5563;">Generate telaah otomatis, isi formulir ulasan, dan unduh berkas hasil ulasan.</span>
                    </div>
                </div>
                <button type="button" class="pkp_button" style="background: #16a34a; color: #ffffff; font-weight: bold; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; white-space: nowrap;" onclick="runAiReviewerAction(this, '{$geminiReviewUrl|escape:"javascript"}', '{$submission->getId()|escape:"javascript"}')">
                    ⚡ Generate &amp; Append to Docx
                </button>
            </div>

            <script type="text/javascript">
            function runAiReviewerAction(btn, ajaxUrl, subId) {ldelim}
                if (!confirm("Jalankan telaah otomatis dengan Gemini AI? Ulasan akan dimasukkan ke teks ulasan reviewer dan berkas DOCX bertelaah otomatis diunduh.")) return;
                var origText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = "⏳ Menganalisis naskah & menyiapkan berkas...";

                $.ajax({ldelim}
                    url: ajaxUrl,
                    type: "POST",
                    dataType: "json",
                    data: {ldelim}
                        reviewAssignmentId: '{$reviewAssignment->getId()|escape:"javascript"}',
                        stageId: '{$reviewAssignment->getStageId()|escape:"javascript"}'
                    {rdelim},
                    success: function(data) {ldelim}
                        btn.disabled = false;
                        btn.innerHTML = origText;

                        if (data.status) {ldelim}
                            var reviewText = data.review;

                            // Isi kolom komentar reviewer
                            if (typeof tinyMCE !== "undefined") {ldelim}
                                var ed = tinyMCE.get("comments") || tinyMCE.get("authorComments") || tinyMCE.activeEditor;
                                if (ed) {ldelim}
                                    var htmlInsert = "<p>" + reviewText.split("\n\n").join("</p><p>").split("\n").join("<br>") + "</p>";
                                    ed.setContent(htmlInsert);
                                {rdelim}
                            {rdelim}
                            var ta = document.getElementById("comments") || document.getElementById("authorComments");
                            if (ta) {ldelim}
                                ta.value = reviewText;
                            {rdelim}

                            // Download berkas DOCX
                            if (data.docxBase64) {ldelim}
                                var mime = (data.fileType === 'docx') ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'text/plain;charset=utf-8';
                                var byteCharacters = atob(data.docxBase64);
                                var byteNumbers = new Array(byteCharacters.length);
                                for (var i = 0; i < byteCharacters.length; i++) {ldelim}
                                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                                {rdelim}
                                var byteArray = new Uint8Array(byteNumbers);
                                var blob = new Blob([byteArray], {ldelim}type: mime{rdelim});

                                var link = document.createElement("a");
                                link.style.display = "none";
                                link.href = window.URL.createObjectURL(blob);
                                link.setAttribute("download", data.filename || ("Reviewed_Manuscript_" + subId + "." + data.fileType));
                                document.body.appendChild(link);
                                link.click();
                                setTimeout(function() {ldelim}
                                    document.body.removeChild(link);
                                    window.URL.revokeObjectURL(link.href);
                                {rdelim}, 300);
                            {rdelim}

                            // Muat ulang tabel reviewer files jika terpasang
                            if ($('#reviewAttachmentsGridContainer').length && $('#reviewAttachmentsGridContainer').data('pkpHandler')) {ldelim}
                                $('#reviewAttachmentsGridContainer').pkpHandler('reload');
                            {rdelim}

                            alert("Ulasan AI berhasil diisi dan berkas DOCX bertelaah otomatis diunduh!");
                        {rdelim} else {ldelim}
                            alert("Pesan: " + data.message);
                        {rdelim}
                    {rdelim},
                    error: function(xhr, status, err) {ldelim}
                        btn.disabled = false;
                        btn.innerHTML = origText;
                        alert("Gagal memproses review: " + err);
                    {rdelim}
                {rdelim});
            {rdelim}
            </script>
        {/if}
    {/if}
    {* --- END GEMINI AI REVIEWER ACTION BAR --- *}
    
    {if $reviewForm}
        {fbvFormSection}
            <h3>{$reviewForm->getLocalizedTitle()|escape}</h3>
            <p>{$reviewForm->getLocalizedDescription()}</p>

            {include file="reviewer/review/reviewFormResponse.tpl"}
        {/fbvFormSection}    
    {else}
        {fbvFormSection label="submission.review" description="reviewer.submission.reviewDescription"}
            {fbvFormSection label="submission.comments.canShareWithAuthor"}
                {fbvElement type="textarea" id="comments" name="comments" value=$comments readonly=$reviewIsClosed rich=true}
            {/fbvFormSection}
            {fbvFormSection label="submission.comments.cannotShareWithAuthor"}
                {fbvElement type="textarea" id="commentsPrivate" name="commentsPrivate" value=$commentsPrivate readonly=$reviewIsClosed rich=true}
            {/fbvFormSection}
        {/fbvFormSection}
    {/if}

    {fbvFormSection label="common.upload" description="reviewer.submission.uploadDescription"}
        {capture assign="reviewAttachmentsGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.ReviewerReviewAttachmentsGridHandler" op="fetchGrid" assocType=$smarty.const.ASSOC_TYPE_REVIEW_ASSIGNMENT assocId=$submission->getReviewId() submissionId=$submission->getId() stageId=$submission->getStageId() reviewIsClosed=$reviewIsClosed escape=false}{/capture}
        {load_url_in_div id="reviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
    {/fbvFormSection}

    {capture assign="queriesGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW escape=false}{/capture}
    {load_url_in_div id="queriesGrid" url=$queriesGridUrl}    

    {$additionalFormFields}

    {capture assign="cancelUrl"}{url page="reviewer" op="submission" path=$submission->getId() step=2 escape=false}{/capture}
    {fbvFormButtons submitText="reviewer.submission.submitReview" confirmSubmit="reviewer.confirmSubmit" saveText="reviewer.submission.saveReviewForLater" cancelText="navigation.goBack" cancelUrl=$cancelUrl cancelUrlTarget="_self" submitDisabled=$reviewIsClosed}
{/fbvFormArea}

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>