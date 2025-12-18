import { useRef } from 'react'
import { useReactToPrint } from 'react-to-print'

interface ReceiptItem {
  product_name: string
  variant_name?: string
  quantity: number
  price: number
  image?: string
}

interface ReceiptProps {
  isOpen: boolean
  onClose: () => void
  orderData: {
    order_no: string
    created_at: string
    items: ReceiptItem[]
    first_name: string
    last_name: string
    email: string
    contact_number: string
    address: string
    payment_method: string
    total: number
    tracking_number?: string
  }
}

const Receipt = ({ isOpen, onClose, orderData }: ReceiptProps) => {
  const receiptRef = useRef<HTMLDivElement>(null)

  const handlePrint = useReactToPrint({
    contentRef: receiptRef,
  })

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70] p-4">
      <div className="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-xl">
        <div className="p-6">
          {/* Header with Close and Print buttons */}
          <div className="flex justify-between items-center mb-6">
            <h2 className="font-lilita text-2xl text-dark-purple">Order Receipt</h2>
            <div className="flex gap-2">
              <button
                onClick={handlePrint}
                className="px-4 py-2 bg-purple text-white rounded-full font-outfit font-semibold hover:bg-dark-purple transition"
              >
                Print Receipt
              </button>
              <button
                onClick={onClose}
                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-full font-outfit font-semibold hover:bg-gray-50 transition"
              >
                Close
              </button>
            </div>
          </div>

          {/* Receipt Content */}
          <div ref={receiptRef} className="p-8 bg-white">
            {/* Logo and Store Info */}
            <div className="text-center mb-6 border-b pb-6">
              <img src="/img/cr8-logo.png" alt="CR8 Logo" className="w-24 h-24 mx-auto mb-4" />
              <h1 className="font-poetsen text-3xl text-darkest-purple mb-2">CR8 Cebu</h1>
              <p className="text-gray-600 font-outfit text-sm">Creative Marketplace</p>
              <p className="text-gray-600 font-outfit text-sm">cr8.dcism.org</p>
            </div>

            {/* Order Information */}
            <div className="mb-6 border-b pb-6">
              <div className="grid grid-cols-2 gap-4 font-outfit">
                <div>
                  <p className="text-sm text-gray-600">Order Number</p>
                  <p className="font-bold text-lg">#{orderData.order_no}</p>
                </div>
                <div className="text-right">
                  <p className="text-sm text-gray-600">Order Date</p>
                  <p className="font-semibold">{new Date(orderData.created_at).toLocaleString()}</p>
                </div>
              </div>
            </div>

            {/* Customer Information */}
            <div className="mb-6 border-b pb-6">
              <h3 className="font-lilita text-dark-purple text-lg mb-3">Customer Details</h3>
              <div className="font-outfit text-sm space-y-2">
                <p><span className="text-gray-600">Name:</span> <span className="font-semibold">{orderData.first_name} {orderData.last_name}</span></p>
                <p><span className="text-gray-600">Email:</span> <span className="font-semibold">{orderData.email}</span></p>
                <p><span className="text-gray-600">Contact:</span> <span className="font-semibold">{orderData.contact_number}</span></p>
                <p><span className="text-gray-600">Shipping Address:</span> <span className="font-semibold">{orderData.address}</span></p>
              </div>
            </div>

            {/* Order Items */}
            <div className="mb-6 border-b pb-6">
              <h3 className="font-lilita text-dark-purple text-lg mb-3">Items Ordered</h3>
              <table className="w-full font-outfit text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2">Product</th>
                    <th className="text-center py-2">Qty</th>
                    <th className="text-right py-2">Price</th>
                    <th className="text-right py-2">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  {orderData.items.map((item, index) => {
                    const itemPrice = item.price ? parseFloat(item.price.toString()) : 0;
                    const itemSubtotal = item.quantity * itemPrice;
                    
                    return (
                      <tr key={index} className="border-b">
                        <td className="py-3">
                          <div>
                            <p className="font-semibold">{item.product_name}</p>
                            {item.variant_name && <p className="text-xs text-gray-500">({item.variant_name})</p>}
                          </div>
                        </td>
                        <td className="text-center py-3">{item.quantity}</td>
                        <td className="text-right py-3">₱{itemPrice.toFixed(2)}</td>
                        <td className="text-right py-3 font-semibold">₱{itemSubtotal.toFixed(2)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            {/* Payment Summary */}
            <div className="mb-6 border-b pb-6">
              <div className="flex justify-between items-center font-outfit mb-2">
                <span className="text-gray-600">Payment Method:</span>
                <span className="font-semibold uppercase">{orderData.payment_method}</span>
              </div>
              {orderData.tracking_number && (
                <div className="flex justify-between items-center font-outfit">
                  <span className="text-gray-600">Tracking Number:</span>
                  <span className="font-mono font-semibold text-purple">{orderData.tracking_number}</span>
                </div>
              )}
            </div>

            {/* Total */}
            <div className="flex justify-between items-center text-xl font-lilita mb-6">
              <span className="text-dark-purple">TOTAL:</span>
              <span className="text-purple">₱{parseFloat(orderData.total.toString()).toFixed(2)}</span>
            </div>

            {/* Footer */}
            <div className="text-center text-gray-500 text-sm font-outfit pt-6 border-t">
              <p>Thank you for your order!</p>
              <p className="mt-2">For inquiries, please contact us through our website.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Receipt
