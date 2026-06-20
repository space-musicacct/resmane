import React, { useState } from 'react';

export default function RecordNewPage() {
  const [purchaseDate, setPurchaseDate] = useState('');
  const [amountTypeId, setAmountTypeId] = useState('');
  const [amount, setAmount] = useState('');
  const [details, setDetails] = useState('');
  const [kakeiboDefaultCategoryId, setKakeiboDefaultCategoryId] = useState('');
  const amountypes = [
    { id: 1, name: '支出' }
  ];
  const categorides = [
    { id: 1, name: '交通費' },
    { id: 2, name: '食費' },
    { id: 3, name: 'サブスク' },
    { id: 4, name: '固定費' },
  ];


  const submit = async () => {
    const data = {
      purchaseDate,
      amountTypeId: Number(amountTypeId),
      amount: Number(amount),
      details,
      kakeiboDefaultCategoryId: Number(kakeiboDefaultCategoryId),
    };
    console.log(data);
  }
  return (
    <div className="min-h-screen bg-[#D7A10A] flex justify-center pt-10">
      <div className="w-[380px] min-h-[600px] bg-[#F4F1F1] rounded-[40px] p-8">
        <div className="flex flex-col items-center">
          <h1 className="text-5xl font-bold text-center mb-12">家計簿登録</h1>

          <div className="flex items-center mb-3">
            <label className="w-24">購入日</label>
            <input
              type="date"
              value={purchaseDate}
              onChange={(e) => setPurchaseDate(e.target.value)}
              className="border border-gray-500 w-44 h-8 px-2"
            />
          </div>

          <div className="flex items-center mb-3">
            <label className="w-24">種類：</label>
            <select
              value={amountTypeId}
              onChange={(e) => setAmountTypeId(e.target.value)}
              className="border border-gray-500 w-44 h-8"
            >
              <option value="">収入</option>
              {amountypes.map((type) => (
                <option key={type.id} value={type.id}>
                  {type.name}
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center mb-3">
            <label className="w-24">金額</label>
            <input
              type="number"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              className="border border-gray-500 w-44 h-8 px-2"
            />
          </div>

          <div className="flex items-start mb-3">
            <label className="w-24 pt-2">内容：</label>
            <textarea
              value={details}
              onChange={(e) => setDetails(e.target.value)}
              className="border border-gray-500 w-44 h-32 p-2 resize-none"
            />
          </div>

          <div className="flex items-center mb-8">
            <label className="w-24">カテゴリー:</label>
            <select
              value={kakeiboDefaultCategoryId}
              onChange={(e) => setKakeiboDefaultCategoryId(e.target.value)}
              className="border border-gray-500 w-44 h-8"
            >
              <option value="">娯楽費</option>
              {categorides.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          </div>

          <button onClick={submit} className="block mx-auto w-64 h-20 rounded-3xl bg-lime-400
          bg-[#A6E01A] text-4xl font-bold mt-10 hover:bg-lime-500">登録</button>
        </div >
      </div >
    </div>
  );


}