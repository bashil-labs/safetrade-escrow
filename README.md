# SafeTrade Escrow Platform

A web-based escrow application designed to secure peer-to-peer transactions in Malawi. It acts as a trusted middleman, holding funds safely until both the buyer and the seller fulfill their obligations.

## 🛠️ Tech Stack
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP (mysqli)
- **Database:** MySQL
- **Server:** Laragon (Apache)

## ✨ Features
- **User Authentication:** Secure registration and login using PHP sessions and password hashing.
- **Wallet System:** Simulated mobile money wallet with top-up functionality.
- **Deal Creation:** Sellers can create a deal and invite a specific buyer via phone number.
- **Escrow Flow:** Buyer pays into escrow -> Seller delivers -> Buyer confirms -> Funds released to seller.
- **Dispute Resolution:** Both parties can open a dispute. 
- **Evidence Upload:** Buyers and sellers can upload image/PDF evidence during a dispute.
- **Admin Panel:** Admins receive alerts, review evidence, and can either refund the buyer or release funds to the seller.

## 🚀 How to Run This Project Locally

1. **Install Laragon**  and start Apache and MySQL.
2. **Clone the repository** into your `www` folder:
   ```bash
   git clone https://github.com/bashil-labs/safetrade-escrow.git