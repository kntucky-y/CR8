import Navbar from '../components/Navbar'

const OrderHistory = () => {
  return (
    <div className="bg-bg-color min-h-screen relative overflow-x-hidden">
      {/* Background decorations */}
      <div className="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <img
          src="/img/bubber.png"
          alt="Decoration"
          className="absolute top-20 right-0 w-1/6 opacity-30 animate-float hidden lg:block"
        />
        <img
          src="/img/blubber.png"
          alt="Decoration"
          className="absolute top-[500px] left-0 w-1/6 opacity-30 animate-float hidden lg:block"
        />
      </div>

      <div className="relative z-10">
        <div className="px-4 md:px-10 lg:px-20 mx-auto">
          <Navbar showSearch={true} />
        </div>
        <main className="px-4 md:px-10 lg:px-20 mx-auto py-12">
          <h1 className="font-poetsen text-darkest-purple text-4xl mb-8">Order History</h1>
        </main>
      </div>
    </div>
  )
}

export default OrderHistory
