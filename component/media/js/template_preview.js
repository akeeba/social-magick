/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

((window, document) =>
{
    const onSocialMagickSampleClick = (e) =>
    {
        const elTarget      = e.currentTarget;
        const sampleKey     = elTarget.dataset.samplekey;
        const sampleOptions = Joomla.getOptions("socialmagick_preview_samples");

        document.getElementById("socialMagicSampleDescription").innerText =
            Joomla.Text._("COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_IMAGE_OPT_" + sampleKey.toUpperCase());

        const elCredits = document.getElementById("socialMagicSampleCredits");
        elCredits.setAttribute("href", sampleOptions[sampleKey].source);
        elCredits.innerText = sampleOptions[sampleKey].credits;

        document.getElementById("socialMagicSampleWidth").innerText  = sampleOptions[sampleKey].width;
        document.getElementById("socialMagicSampleHeight").innerText = sampleOptions[sampleKey].height;
    };

    const onSocialMagickRegenerateTemplatePreview = async (e) =>
    {
        e.preventDefault();

        const formData = new FormData(document.forms["adminForm"]);
        const elImage = document.getElementById("socialmagic_preview_img");
        const elBtn = document.getElementById("socialmagic_preview_link");

        elImage.src = '../media/com_socialmagick/images/loading.svg';
        elBtn.setAttribute("href", '#');
        elBtn.classList.add('disabled');

        formData.set('task', 'regeneratePreview');

        console.log(Object.fromEntries(formData));

        const response = await fetch(
            "index.php?option=com_socialmagick&view=templates&task=regeneratePreview&format=json",
            {
                method: "POST",
                body:   formData,
            }
        )

        if (!response.ok)
        {
            Joomla.renderMessages({
                error: [Joomla.Text._("COM_SOCIALMAGICK_TEMPLATE_ERR_REGEN_NETWORK") + response.statusText]
            })
        }

        var data = {};

        try
        {
            data = await response.json();
        }
        catch (err)
        {
            data = {};

            Joomla.renderMessages({
                error: [err.message]
            })
        }

        if (data?.image)
        {
            elImage.src = data.image;
            elBtn.setAttribute("href", data.image);
            elBtn.classList.remove('disabled');
        }
        else
        {
            elImage.src = '../media/com_socialmagick/images/nope.svg';
        }
    };

    // Initialisation
    [].slice.call(document.querySelectorAll(".socialMagickPreviewSample")).forEach((el) =>
    {
        el.addEventListener("click", onSocialMagickSampleClick);
    });

    document.getElementById('socialmagic_preview_refresh')?.addEventListener('click', onSocialMagickRegenerateTemplatePreview);
})(window, document);
