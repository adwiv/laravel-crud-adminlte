<script>
    function slugify(text) {
        return text.toString().toLowerCase().trim()
            .replace(/&/g, '-and-')         // Replace & with 'and'
            .replace(/[\s\W-]+/g, '-')      // Replace spaces, non-word characters and dashes with a single dash (-)
            .replace(/-$/, '');             // Remove last floating dash if exists
    }

    $(document).ready(function () {
        $(".slug-source").bind("propertychange keyup input cut paste", function (event) {
            let slugId = $(this).data('slug-sink');

            let slug = $("#" + slugId);
            if (slug) {
                let text = slugify($(this).val());
                slug.val(text);
            }
        });
    });
</script>
