import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

interface Variant {
  id: number;
  variant_name: string;
  quantity: number;
  price: number;
  image: string | null;
}

interface Product {
  id: number;
  product_name: string;
  description: string;
  price: number;
  quantity: number;
  image: string;
  variants: Variant[];
}

const ManageProducts = () => {
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({
    product_name: '',
    description: '',
    price: '',
    quantity: '',
  });
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [hasVariants, setHasVariants] = useState(false);
  const [variants, setVariants] = useState<Array<{name: string; quantity: string; price: string; image: File | null}>>([{name: '', quantity: '', price: '', image: null}]);
  const [submitting, setSubmitting] = useState(false);
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const navigate = useNavigate();

  useEffect(() => {
    fetchProducts();
  }, []);

  const fetchProducts = async () => {
    try {
      const response = await fetch('/api/artist_products.php', {
        credentials: 'include'
      });
      const data = await response.json();
      console.log('Fetched products data:', data);
      if (data.success) {
        setProducts(data.products || []);
      }
    } catch (error) {
      console.error('Error fetching products:', error);
    } finally {
      setLoading(false);
    }
  };

  const resetForm = () => {
    setEditingProduct(null);
    setFormData({
      product_name: '',
      description: '',
      price: '',
      quantity: ''
    });
    setImageFile(null);
    setHasVariants(false);
    setVariants([{name: '', quantity: '', price: '', image: null}]);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);

    const formDataObj = new FormData();
    formDataObj.append('product_name', formData.product_name);
    formDataObj.append('description', formData.description);
    formDataObj.append('price', formData.price);
    formDataObj.append('quantity', formData.quantity);
    formDataObj.append('has_variants', hasVariants ? '1' : '0');
    
    // Add product ID if editing
    if (editingProduct) {
      formDataObj.append('product_id', editingProduct.id.toString());
    }
    
    // Always upload main product image if provided
    if (imageFile) {
      formDataObj.append('image', imageFile);
    }
    
    if (hasVariants) {
      const validVariants = variants.filter(v => v.name.trim() !== '');
      formDataObj.append('variants', JSON.stringify(validVariants.map(v => ({
        name: v.name,
        quantity: v.quantity,
        price: v.price
      }))));
      
      validVariants.forEach((variant, index) => {
        if (variant.image) {
          formDataObj.append(`variant_image_${index}`, variant.image);
        }
      });
    }

    try {
      // Use different endpoint for editing vs adding
      const endpoint = editingProduct ? '/api/update_product.php' : '/api/add_product.php';
      const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'include',
        body: formDataObj
      });
      const data = await response.json();
      
      if (data.success) {
        alert(editingProduct ? 'Product updated successfully!' : 'Product added successfully!');
        setShowModal(false);
        resetForm();
        fetchProducts();
      } else {
        alert(data.message || 'Error adding product');
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Error adding product');
    } finally {
      setSubmitting(false);
    }
  };

  const addVariant = () => {
    setVariants([...variants, {name: '', quantity: '', price: '', image: null}]);
  };

  const removeVariant = (index: number) => {
    const newVariants = variants.filter((_, i) => i !== index);
    if (newVariants.length === 0) {
      // If removing the last variant, uncheck the checkbox and reset
      setHasVariants(false);
      setVariants([{name: '', quantity: '', price: '', image: null}]);
    } else {
      setVariants(newVariants);
    }
  };

  const updateVariantField = (index: number, field: 'name' | 'quantity' | 'price', value: string) => {
    const newVariants = [...variants];
    newVariants[index][field] = value;
    setVariants(newVariants);
  };

  const updateVariantImage = (index: number, file: File | null) => {
    const newVariants = [...variants];
    newVariants[index].image = file;
    setVariants(newVariants);
  };

  const handleDelete = async (productId: number) => {
    if (!confirm('Are you sure you want to delete this product?')) return;

    try {
      const formData = new FormData();
      formData.append('product_id', productId.toString());

      const response = await fetch('/api/delete_product.php', {
        method: 'POST',
        credentials: 'include',
        body: formData
      });
      const data = await response.json();

      if (data.success) {
        alert('Product deleted successfully');
        fetchProducts();
      } else {
        alert(data.message || 'Error deleting product');
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Error deleting product');
    }
  };

  const handleEdit = (product: Product) => {
    console.log('Editing product:', product);
    console.log('Product variants:', product.variants);
    
    setEditingProduct(product);
    setFormData({
      product_name: product.product_name,
      description: product.description,
      price: product.price.toString(),
      quantity: product.quantity.toString()
    });
    
    // Reset image file but keep reference to existing image
    setImageFile(null);
    
    // Set variants if product has them
    if (product.variants && product.variants.length > 0) {
      console.log('Product has variants, setting them:', product.variants);
      setHasVariants(true);
      setVariants(product.variants.map(v => ({
        name: v.variant_name,
        quantity: v.quantity.toString(),
        price: v.price.toString(),
        image: null // Existing image, will be displayed separately
      })));
    } else {
      console.log('Product has no variants');
      setHasVariants(false);
      setVariants([{name: '', quantity: '', price: '', image: null}]);
    }
    
    setShowModal(true);
  };

  return (
    <div className="min-h-screen bg-cream py-8">
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-4xl font-outfit font-bold text-dark-purple">Manage Products</h1>
          <button
            onClick={() => navigate('/dashboard')}
            className="px-4 py-2 bg-gray-200 text-dark-purple rounded-lg hover:bg-gray-300"
          >
            Back to Dashboard
          </button>
        </div>

        {loading ? (
          <p>Loading products...</p>
        ) : (
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-2xl font-outfit font-semibold">Your Products</h2>
              <button 
                onClick={() => {
                  resetForm();
                  setShowModal(true);
                }}
                className="px-4 py-2 bg-purple text-white rounded-lg hover:bg-opacity-90"
              >
                Add New Product
              </button>
            </div>
            
            {products.length === 0 ? (
              <p className="text-gray-500">No products yet. Start by adding your first product!</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b">
                      <th className="text-left py-3 px-4">Image</th>
                      <th className="text-left py-3 px-4">Product Name</th>
                      <th className="text-left py-3 px-4">Price</th>
                      <th className="text-left py-3 px-4">Stock</th>
                      <th className="text-left py-3 px-4">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {products.map(product => (
                      <tr key={product.id} className="border-b hover:bg-gray-50">
                        <td className="py-3 px-4">
                          <img 
                            src={`https://cr8.dcism.org/${product.image}`} 
                            alt={product.product_name} 
                            className="w-16 h-16 object-cover rounded"
                            onError={(e) => { 
                              (e.target as HTMLImageElement).src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="64" height="64"%3E%3Crect width="64" height="64" fill="%23ddd"/%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-family="monospace" font-size="14" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E';
                            }}
                          />
                        </td>
                        <td className="py-3 px-4">{product.product_name}</td>
                        <td className="py-3 px-4">₱{parseFloat(product.price.toString()).toFixed(2)}</td>
                        <td className="py-3 px-4">{product.quantity}</td>
                        <td className="py-3 px-4">
                          <button onClick={() => handleEdit(product)} className="text-blue-600 hover:underline mr-3">Edit</button>
                          <button onClick={() => handleDelete(product.id)} className="text-red-600 hover:underline">Delete</button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Add Product Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <h2 className="text-2xl font-outfit font-bold mb-6">{editingProduct ? 'Edit Product' : 'Add New Product'}</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                <input
                  type="text"
                  required
                  value={formData.product_name}
                  onChange={(e) => setFormData({...formData, product_name: e.target.value})}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea
                  required
                  rows={4}
                  value={formData.description}
                  onChange={(e) => setFormData({...formData, description: e.target.value})}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Price (₱)</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    value={formData.price}
                    onChange={(e) => setFormData({...formData, price: e.target.value})}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                  <input
                    type="number"
                    required
                    value={formData.quantity}
                    onChange={(e) => setFormData({...formData, quantity: e.target.value})}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent"
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Product Image</label>
                {editingProduct && editingProduct.image && (
                  <div className="mb-2">
                    <p className="text-sm text-gray-600 mb-1">Current image:</p>
                    <img src={`https://cr8.dcism.org/${editingProduct.image}`} alt="Current" className="h-20 w-20 object-cover rounded" />
                  </div>
                )}
                <input
                  type="file"
                  accept="image/*"
                  required={!editingProduct}
                  onChange={(e) => setImageFile(e.target.files?.[0] || null)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
                />
                {editingProduct && <p className="text-xs text-gray-500 mt-1">Leave empty to keep current image</p>}
              </div>
              
              <div className="border-t pt-4">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={hasVariants}
                    onChange={(e) => {
                      setHasVariants(e.target.checked);
                      if (!e.target.checked) setVariants([{name: '', quantity: '', price: '', image: null}]);
                    }}
                    className="w-4 h-4 text-purple focus:ring-purple border-gray-300 rounded"
                  />
                  <span className="text-sm font-medium text-gray-700">This product has variants</span>
                </label>
              </div>

              {hasVariants && (
                <div className="space-y-4">
                  <label className="block text-sm font-medium text-gray-700">Variants</label>
                  {variants.map((variant, index) => (
                    <div key={index} className="border border-gray-200 rounded-lg p-4 space-y-3">
                      <div className="flex justify-between items-center">
                        <span className="font-medium text-gray-700">Variant {index + 1}</span>
                        <button
                          type="button"
                          onClick={() => removeVariant(index)}
                          className="text-sm text-red-600 hover:underline"
                        >
                          Remove
                        </button>
                      </div>
                      <input
                        type="text"
                        value={variant.name}
                        onChange={(e) => updateVariantField(index, 'name', e.target.value)}
                        placeholder="Variant name (e.g., Red, Large)"
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent"
                      />
                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">Price (₱)</label>
                          <input
                            type="number"
                            step="0.01"
                            value={variant.price}
                            onChange={(e) => updateVariantField(index, 'price', e.target.value)}
                            placeholder="Price"
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent"
                          />
                        </div>
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">Quantity</label>
                          <input
                            type="number"
                            value={variant.quantity}
                            onChange={(e) => updateVariantField(index, 'quantity', e.target.value)}
                            placeholder="Stock"
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent"
                          />
                        </div>
                      </div>
                      <div>
                        <label className="block text-xs text-gray-600 mb-1">Image</label>
                        {editingProduct && editingProduct.variants && editingProduct.variants[index]?.image && (
                          <div className="mb-1">
                            <img src={`https://cr8.dcism.org/${editingProduct.variants[index].image}`} alt="Current variant" className="h-16 w-16 object-cover rounded" />
                            <p className="text-xs text-gray-500">Current image</p>
                          </div>
                        )}
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => updateVariantImage(index, e.target.files?.[0] || null)}
                          className="w-full border border-gray-300 rounded-lg px-3 py-1 text-sm"
                        />
                        {editingProduct && <p className="text-xs text-gray-500">Leave empty to keep current image</p>}
                      </div>
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={addVariant}
                    className="text-sm text-purple hover:underline"
                  >
                    + Add Another Variant
                  </button>
                </div>
              )}
              
              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => {
                    setShowModal(false);
                    resetForm();
                  }}
                  className="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="px-6 py-2 bg-purple text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50"
                >
                  {submitting ? (editingProduct ? 'Updating...' : 'Adding...') : (editingProduct ? 'Update Product' : 'Add Product')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default ManageProducts;
