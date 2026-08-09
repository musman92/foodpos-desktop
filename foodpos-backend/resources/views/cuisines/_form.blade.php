@php
    use Illuminate\Support\Facades\Storage;
    
    $isEdit = isset($cuisine) && $cuisine->exists;
    $formAction = $isEdit ? route('cuisines.update', $cuisine) : route('cuisines.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $cuisineData = $isEdit ? $cuisine->toArray() : [];
    $title = $isEdit ? 'Edit Cuisine' : 'Create New Cuisine';
    $subtitle = $isEdit ? 'Update cuisine information' : 'Add a new cuisine type for your menu items';
    $buttonText = $isEdit ? 'Update Cuisine' : 'Create Cuisine';
@endphp

<div class="max-w-4xl mx-auto" x-data="cuisineForm({{ json_encode($cuisineData) }}, {{ $isEdit ? 'true' : 'false' }})">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        </div>

        <form action="{{ $formAction }}" method="POST" class="p-6 space-y-6" enctype="multipart/form-data" @submit.prevent="submitForm">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <!-- Basic Information -->
            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Basic Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Cuisine Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Cuisine Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               x-model="formData.name"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                               placeholder="Italian, Chinese, Mexican...">
                        <p class="mt-1 text-xs text-gray-500">Slug will be generated automatically from the name</p>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Sort Order -->
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">
                            Sort Order
                        </label>
                        <input type="number" 
                               name="sort_order" 
                               id="sort_order" 
                               min="0"
                               x-model="formData.sort_order"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('sort_order') border-red-500 @enderror"
                               placeholder="0">
                        <p class="mt-1 text-xs text-gray-500">Lower numbers appear first</p>
                        @error('sort_order')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea name="description" 
                              id="description" 
                              rows="3"
                              x-model="formData.description"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror"
                              placeholder="Brief description of this cuisine type..."></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Image -->
            <div class="space-y-6 pt-6 border-t border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Cuisine Image</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Image Upload -->
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                            Cuisine Image
                        </label>
                        <input type="file" 
                               name="image" 
                               id="image" 
                               accept="image/*"
                               @change="previewImage($event)"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 @error('image') border-red-500 @enderror">
                        <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF, WebP up to 2MB</p>
                        @error('image')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Image Preview -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Preview
                        </label>
                        <div class="mt-1">
                            <img x-show="imagePreview" 
                                 :src="imagePreview" 
                                 alt="Cuisine preview" 
                                 class="h-32 w-32 rounded-lg object-cover border border-gray-300">
                            @if($isEdit && $cuisine->image)
                                <img x-show="!imagePreview" 
                                     src="{{ Storage::url($cuisine->image) }}" 
                                     alt="{{ $cuisine->name }}" 
                                     class="h-32 w-32 rounded-lg object-cover border border-gray-300">
                            @else
                                <div x-show="!imagePreview" 
                                     class="h-32 w-32 rounded-lg bg-gray-100 border border-gray-300 flex items-center justify-center">
                                    <i class="fas fa-image text-gray-400 text-2xl"></i>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="space-y-6 pt-6 border-t border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Status</h2>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="is_active" 
                               value="1"
                               x-model="formData.is_active"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Active (Cuisine will be visible in menu)</span>
                    </label>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('cuisines.index') }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    {{ $buttonText }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function cuisineForm(cuisineData = null, isEdit = false) {
    return {
        formData: {
            name: cuisineData?.name || '',
            description: cuisineData?.description || '',
            sort_order: cuisineData?.sort_order ?? 0,
            is_active: cuisineData?.is_active ?? true,
        },
        imagePreview: null,

        previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.imagePreview = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        },

        submitForm(event) {
            // Let the form submit normally
            event.target.submit();
        }
    }
}
</script>

