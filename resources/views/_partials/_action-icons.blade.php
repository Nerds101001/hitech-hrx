<div class="d-flex align-items-center gap-2">
  @if(isset($show))
    <a href="{{ $show }}"
       class="btn btn-icon me-2"
       data-bs-toggle="tooltip"
       title="View">
      <i class="bx bx-show"></i>
    </a>
  @endif

  @if(isset($edit))
    <!-- Edit Button -->
    <a href="{{$edit}}"
       class="btn btn-icon me-2"
       data-bs-toggle="tooltip"
       title="Edit">
      <i class="fa fa-edit"></i>
    </a>
  @endif

  @if(isset($delete))
    <!-- Delete Button -->
    <form action=""
          method="POST"
          onsubmit="return confirm('Are you sure you want to delete this record?')">
      @csrf
      @method('DELETE')
      <button type="submit"
              class="btn btn-icon"
              data-bs-toggle="tooltip"
              title="Delete">
        <i class="fa fa-trash"></i>
      </button>
    </form>
  @endif
</div>

