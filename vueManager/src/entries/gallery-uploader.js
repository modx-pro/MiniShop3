import { createApp } from 'vue'
import GalleryUploader from '../components/gallery/GalleryUploader.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'

/**
 * Gallery Uploader Entry Point
 *
 * Initializes Vue application with Uppy uploader for product gallery
 * Integrates into ExtJS panel via DOM mounting
 */

window.MS3_initGalleryUploader = function(config) {
  const {
    containerId = 'ms3-gallery-uploader',
    productId,
    sourceId = 1,
    connectorUrl = ms3.config.connector_url,
    maxFileSize = 10485760,
    maxWidth = 1920,
    maxHeight = 1080,
    allowedFileTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/heic'],
    onUploadSuccess,
    onUploadError,
    onUploadComplete,
  } = config

  const container = document.getElementById(containerId)
  if (!container) {
    console.error(`[MS3 Gallery Uploader] Container #${containerId} not found`)
    return null
  }

  const app = createApp(GalleryUploader, {
    productId,
    sourceId,
    connectorUrl,
    maxFileSize,
    maxWidth,
    maxHeight,
    allowedFileTypes,
    onUploadSuccess,
    onUploadError,
    onUploadComplete,
  })

  const instance = app.mount(container)
  injectFormStylesOverride()

  container.__vueApp__ = app
  container.__vueInstance__ = instance

  return {
    app,
    instance,
    destroy: () => {
      app.unmount()
      delete container.__vueApp__
      delete container.__vueInstance__
    },
  }
}

window.MS3_destroyGalleryUploader = function(containerId = 'ms3-gallery-uploader') {
  const container = document.getElementById(containerId)
  if (container && container.__vueApp__) {
    container.__vueApp__.unmount()
    delete container.__vueApp__
    delete container.__vueInstance__
  }
}
