<script type="text/javascript">
    $(function() {
        $('#geminiReviewerSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    });
</script>

<form class="pkp_form" id="geminiReviewerSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
    {csrf}
    
    {fbvFormSection title="Visibilitas Plugin Gemini AI"}
        <p style="font-size: 12px; color: #555; margin-bottom: 8px;">Tentukan di halaman mana saja tombol telaah naskah AI ditampilkan:</p>
        <ul class="checkbox_and_radiobutton">
            {fbvElement type="checkbox" id="showEditor" name="showEditor" value="1" checked=$showEditor label="Tampilkan di sisi Editor (Modal Keputusan / Send Reviews)" translate=false}
            {fbvElement type="checkbox" id="showReviewer" name="showReviewer" value="1" checked=$showReviewer label="Tampilkan di sisi Reviewer (Langkah 3: Unduh &amp; Telaah)" translate=false}
        </ul>
    {/fbvFormSection}

    {fbvFormSection title="Custom Prompt Instruksi AI"}
        <p style="font-size: 12px; color: #555; margin-bottom: 8px;">Sesuaikan instruksi telaah peer review sesuai pedoman jurnal Anda:</p>
        {fbvElement type="textarea" name="customPrompt" id="customPrompt" value=$customPrompt rich=false size="large"}
    {/fbvFormSection}

    {fbvFormButtons submitText="common.save"}
</form>