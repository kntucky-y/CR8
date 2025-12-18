import Navbar from '../components/Navbar'

const OrderHistory = () => {
  return (
    <div className="bg-bg-color min-h-screen">
      <div className="px-4 md:px-10 lg:px-20 mx-auto">
        <Navbar showSearch={true} />
      </div>
      <main className="px-4 md:px-10 lg:px-20 mx-auto py-12">
        <h1 className="font-poetsen text-darkest-purple text-4xl mb-8">Order History</h1>
      </main>
    </div>
  )
}

export default OrderHistory
