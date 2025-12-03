<template>
  <div class="logo-uploader">
    <!-- Current Logo Display -->
    <div v-if="currentLogo" class="current-logo mb-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-medium">Current Logo</h4>
        <button 
          @click="removeLogo"
          class="btn btn-sm btn-danger"
          :disabled="uploading"
        >
          Remove
        </button>
      </div>
      <div class="logo-preview">
        <img 
          :src="currentLogo" 
          alt="Current logo" 
          class="max-h-16 max-w-full object-contain"
          @error="handleImageError"
        />
      </div>
    </div>

    <!-- Upload Area -->
    <div 
      class="upload-area"
      :class="{ 
        'dragover': dragover,
        'uploading': uploading,
        'has-error': uploadError
      }"
      @drop="handleDrop"
      @dragover="handleDragOver"
      @dragleave="handleDragLeave"
      @click="openFileDialog"
    >
      <input 
        ref="fileInput"
        type="file" 
        accept="image/*"
        @change="handleFileSelect"
        class="hidden"
      />
      
      <div class="upload-content">
        <div v-if="uploading" class="upload-progress">
          <div class="spinner"></div>
          <p class="mt-2">Uploading logo...</p>
          <div class="progress-bar">
            <div class="progress-fill" :style="{ width: uploadProgress + '%' }"></div>
          </div>
        </div>
        
        <div v-else-if="uploadError" class="upload-error">
          <svg class="w-12 h-12 text-red-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.98-.833-2.75 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
          </svg>
          <p class="text-red-600 font-medium">Upload Failed</p>
          <p class="text-sm text-red-500 mt-1">{{ uploadError }}</p>
          <button @click="resetUpload" class="btn btn-sm mt-2">Try Again</button>
        </div>
        
        <div v-else class="upload-placeholder">
          <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
          </svg>
          <p class="text-lg font-medium mb-2">
            {{ currentLogo ? 'Upload New Logo' : 'Upload Logo' }}
          </p>
          <p class="text-sm text-gray-500 mb-4">
            Drag and drop your logo here, or click to browse
          </p>
          <div class="upload-requirements">
            <p class="text-xs text-gray-400">
              Supported formats: PNG, JPG, SVG, WebP<br>
              Max file size: 2MB<br>
              Recommended: Square aspect ratio, min 200x200px
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Options -->
    <div class="upload-options mt-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Logo Settings -->
        <div class="options-section">
          <h5 class="font-medium mb-2">Logo Settings</h5>
          <div class="space-y-3">
            <div>
              <label class="block text-sm mb-1">Logo Position</label>
              <select v-model="logoSettings.position" class="select w-full">
                <option value="left">Left</option>
                <option value="center">Center</option>
                <option value="right">Right</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm mb-1">Max Height (px)</label>
              <input 
                v-model.number="logoSettings.maxHeight"
                type="number" 
                min="20" 
                max="200"
                class="input w-full"
              />
            </div>
            
            <div class="flex items-center gap-2">
              <input 
                v-model="logoSettings.showOnMobile"
                type="checkbox" 
                id="showOnMobile"
                class="checkbox"
              />
              <label for="showOnMobile" class="text-sm">Show on mobile</label>
            </div>
          </div>
        </div>
        
        <!-- Preview Variations -->
        <div class="options-section">
          <h5 class="font-medium mb-2">Preview</h5>
          <div class="logo-variations">
            <div class="variation" v-if="previewUrl">
              <div class="variation-label">Normal</div>
              <div class="variation-preview bg-white">
                <img :src="previewUrl" :style="logoPreviewStyle" alt="Logo preview" />
              </div>
            </div>
            
            <div class="variation" v-if="previewUrl">
              <div class="variation-label">Dark Mode</div>
              <div class="variation-preview bg-gray-900">
                <img :src="previewUrl" :style="logoPreviewStyle" alt="Logo preview dark" />
              </div>
            </div>
            
            <div class="variation" v-if="previewUrl">
              <div class="variation-label">Small</div>
              <div class="variation-preview bg-gray-100">
                <img :src="previewUrl" :style="{ ...logoPreviewStyle, maxHeight: '24px' }" alt="Logo preview small" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- URL Input Alternative -->
    <div class="url-input-section mt-6 pt-4 border-t">
      <h5 class="font-medium mb-2">Or Use External URL</h5>
      <div class="flex gap-2">
        <input 
          v-model="logoUrl"
          type="url" 
          placeholder="https://example.com/logo.png"
          class="input flex-1"
          @input="validateUrl"
        />
        <button 
          @click="setLogoFromUrl"
          class="btn"
          :disabled="!isValidUrl || uploading"
        >
          Use URL
        </button>
      </div>
      <p class="text-xs text-gray-500 mt-1">
        Enter a direct URL to an image file
      </p>
      <p v-if="urlError" class="text-xs text-red-500 mt-1">{{ urlError }}</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LogoUploader',
  props: {
    currentLogo: {
      type: String,
      default: null
    }
  },
  emits: ['logo-updated', 'logo-removed'],
  data() {
    return {
      dragover: false,
      uploading: false,
      uploadProgress: 0,
      uploadError: null,
      previewUrl: this.currentLogo,
      logoUrl: '',
      urlError: null,
      logoSettings: {
        position: 'left',
        maxHeight: 40,
        showOnMobile: true
      }
    }
  },
  computed: {
    isValidUrl() {
      return this.logoUrl && this.isValidImageUrl(this.logoUrl)
    },
    
    logoPreviewStyle() {
      return {
        maxHeight: this.logoSettings.maxHeight + 'px',
        objectFit: 'contain'
      }
    }
  },
  watch: {
    currentLogo(newVal) {
      this.previewUrl = newVal
    }
  },
  methods: {
    openFileDialog() {
      if (!this.uploading) {
        this.$refs.fileInput.click()
      }
    },

    handleFileSelect(event) {
      const files = event.target.files
      if (files.length > 0) {
        this.handleFile(files[0])
      }
    },

    handleDrop(event) {
      event.preventDefault()
      this.dragover = false
      
      const files = event.dataTransfer.files
      if (files.length > 0) {
        this.handleFile(files[0])
      }
    },

    handleDragOver(event) {
      event.preventDefault()
      this.dragover = true
    },

    handleDragLeave() {
      this.dragover = false
    },

    async handleFile(file) {
      // Validate file
      const validation = this.validateFile(file)
      if (!validation.valid) {
        this.uploadError = validation.error
        return
      }

      // Reset error state
      this.uploadError = null
      this.uploading = true
      this.uploadProgress = 0

      try {
        // Create preview
        const previewUrl = URL.createObjectURL(file)
        this.previewUrl = previewUrl

        // Prepare form data
        const formData = new FormData()
        formData.append('logo', file)
        formData.append('settings', JSON.stringify(this.logoSettings))

        // Upload with progress tracking
        const response = await this.uploadWithProgress(formData)
        
        if (response.success) {
          this.previewUrl = response.logoUrl
          this.$emit('logo-updated', response.logoUrl)
          
          // Cleanup preview URL
          if (previewUrl.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl)
          }
        } else {
          throw new Error(response.message || 'Upload failed')
        }
      } catch (error) {
        this.uploadError = error.message
        console.error('Logo upload error:', error)
      } finally {
        this.uploading = false
        this.uploadProgress = 0
      }
    },

    async uploadWithProgress(formData) {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest()

        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) {
            this.uploadProgress = Math.round((e.loaded / e.total) * 100)
          }
        })

        xhr.addEventListener('load', () => {
          if (xhr.status === 200) {
            try {
              const response = JSON.parse(xhr.responseText)
              resolve(response)
            } catch (e) {
              reject(new Error('Invalid response format'))
            }
          } else {
            reject(new Error(`Upload failed with status ${xhr.status}`))
          }
        })

        xhr.addEventListener('error', () => {
          reject(new Error('Upload failed'))
        })

        xhr.open('POST', '/internal-api/white-label/logo')
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content)
        xhr.send(formData)
      })
    },

    validateFile(file) {
      // Check file type
      const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp']
      if (!allowedTypes.includes(file.type)) {
        return {
          valid: false,
          error: 'Invalid file type. Please use PNG, JPG, SVG, or WebP format.'
        }
      }

      // Check file size (2MB limit)
      const maxSize = 2 * 1024 * 1024 // 2MB
      if (file.size > maxSize) {
        return {
          valid: false,
          error: 'File is too large. Maximum size is 2MB.'
        }
      }

      return { valid: true }
    },

    async removeLogo() {
      if (!confirm('Are you sure you want to remove the current logo?')) {
        return
      }

      try {
        const response = await fetch('/internal-api/white-label/logo', {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          this.previewUrl = null
          this.$emit('logo-removed')
        } else {
          const error = await response.json()
          this.uploadError = error.message || 'Failed to remove logo'
        }
      } catch (error) {
        this.uploadError = 'Failed to remove logo'
      }
    },

    validateUrl() {
      this.urlError = null
      
      if (this.logoUrl && !this.isValidImageUrl(this.logoUrl)) {
        this.urlError = 'Please enter a valid image URL'
      }
    },

    async setLogoFromUrl() {
      if (!this.isValidUrl) return

      this.uploading = true
      this.uploadError = null

      try {
        const response = await fetch('/internal-api/white-label/logo-from-url', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({
            url: this.logoUrl,
            settings: this.logoSettings
          })
        })

        if (response.ok) {
          const data = await response.json()
          this.previewUrl = data.logoUrl
          this.logoUrl = ''
          this.$emit('logo-updated', data.logoUrl)
        } else {
          const error = await response.json()
          this.urlError = error.message || 'Failed to load image from URL'
        }
      } catch (error) {
        this.urlError = 'Failed to load image from URL'
      } finally {
        this.uploading = false
      }
    },

    isValidImageUrl(url) {
      try {
        const urlObj = new URL(url)
        const pathname = urlObj.pathname.toLowerCase()
        const imageExtensions = ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif']
        return imageExtensions.some(ext => pathname.endsWith(ext))
      } catch {
        return false
      }
    },

    handleImageError() {
      this.uploadError = 'Failed to load current logo'
    },

    resetUpload() {
      this.uploadError = null
      this.uploading = false
      this.uploadProgress = 0
    }
  }
}
</script>

<style scoped>
.upload-area {
  @apply border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer transition-all duration-200;
}

.upload-area:hover {
  @apply border-gray-400 bg-gray-50;
}

.upload-area.dragover {
  @apply border-blue-400 bg-blue-50;
}

.upload-area.uploading {
  @apply cursor-not-allowed opacity-75;
}

.upload-area.has-error {
  @apply border-red-300 bg-red-50;
}

.logo-preview {
  @apply border border-gray-200 rounded p-4 bg-gray-50 inline-block;
}

.spinner {
  @apply w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto;
}

.progress-bar {
  @apply w-full bg-gray-200 rounded-full h-2 mt-2;
}

.progress-fill {
  @apply bg-blue-600 h-2 rounded-full transition-all duration-300;
}

.options-section {
  @apply border border-gray-200 rounded p-4;
}

.logo-variations {
  @apply space-y-3;
}

.variation {
  @apply text-center;
}

.variation-label {
  @apply text-xs text-gray-500 mb-1;
}

.variation-preview {
  @apply border rounded p-2 flex items-center justify-center h-12;
}

.btn {
  @apply px-4 py-2 rounded font-medium cursor-pointer border-none transition-colors;
}

.btn-sm {
  @apply px-2 py-1 text-sm;
}

.btn-danger {
  @apply bg-red-600 text-white hover:bg-red-700;
}

.input, .select {
  @apply border border-gray-300 rounded px-3 py-2;
}

.checkbox {
  @apply rounded;
}

.dark .upload-area {
  @apply border-gray-600;
}

.dark .upload-area:hover {
  @apply border-gray-500 bg-gray-800;
}

.dark .upload-area.dragover {
  @apply border-blue-500 bg-blue-900 bg-opacity-20;
}

.dark .logo-preview {
  @apply border-gray-600 bg-gray-800;
}

.dark .options-section {
  @apply border-gray-600;
}

.dark .variation-preview {
  @apply border-gray-600;
}

.dark .input,
.dark .select {
  @apply bg-gray-700 border-gray-600 text-white;
}

.dark .progress-bar {
  @apply bg-gray-700;
}
</style>