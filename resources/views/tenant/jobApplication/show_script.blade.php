<script>
    $(document).ready(function() {
      // Initialize Tagify
      const skillEl = document.querySelector('#skill_input');
      if (skillEl) {
          if (window.Tagify) {
              new Tagify(skillEl);
          }
      }

      /* Rating Logic */
      $('#stars li').on('mouseover', function() {
        var onStar = parseInt($(this).data('value'), 10);
        $(this).parent().children('li.star').each(function(e) {
          if (e < onStar) $(this).addClass('hover');
          else $(this).removeClass('hover');
        });
      }).on('mouseout', function() {
        $(this).parent().children('li.star').each(function(e) {
          $(this).removeClass('hover');
        });
      });

      $('#stars li').off('click').on('click', function() {
        var onStar = parseInt($(this).data('value'), 10);
        var stars = $(this).parent().children('li.star');
        stars.removeClass('selected');
        for (let i = 0; i < onStar; i++) $(stars[i]).addClass('selected');

        $.ajax({
          url: '{{ route('job.application.rating', $jobApplication->id) }}',
          type: 'POST',
          data: {
            rating: onStar,
            "_token": "{{ csrf_token() }}"
          },
          success: function(data) {
             // Optional: toastr
          }
        });
      });

      /* Stage Change Logic */
      $(document).off('change', '.stage-radio').on('change', '.stage-radio', function() {
        var stageId = $(this).val();
        var scheduleId = $(this).data('scheduleid');
        $.ajax({
          url: "{{ route('job.application.stage.change') }}",
          type: 'POST',
          data: {
            "stage": stageId,
            "schedule_id": scheduleId,
            "_token": "{{ csrf_token() }}",
          },
          success: function(data) {
            if ($('#commonModal').is(':visible')) {
                // Refresh modal content instead of full page if in modal
                location.reload(); 
            } else {
                window.location.reload();
            }
          }
        });
      });
    });
</script>
