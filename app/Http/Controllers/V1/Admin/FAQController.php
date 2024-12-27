<?php

namespace App\Http\Controllers\V1\Admin;


use App\Http\Controllers\Controller;
use App\Models\Faq;
use Exception;
use Illuminate\Http\Request;



class FAQController extends Controller
{
    public function fetchFaqList()
    {
        $faqs = Faq::where('status', 'ACTIVE')->get();
        return response()->json([
            'status_code' => 1,
            'data' => ['faqs' => $faqs],
            'message' => 'fetched successfully.'

        ]);
    }


    public function createFaq(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
        ]);

        $faq = Faq::create([
            'question' => $request->input('question'),
            'answer' => $request->input('answer'),
            'status' => $request->input('status', 'ACTIVE'),
        ]);

        return response()->json([
            'status_code' => 1,
            'data' => ['faqs' => $faq],
            'message' => 'created successfully.'
        ]);
    }
    public function updateFaq(Request $request)
    {
        // Validate the request data
        $request->validate([
            'id' => 'required|integer|exists:faqs,id', // Ensure 'id' is provided and exists in 'faqs' table
            'question' => 'required|string|max:255',
            'answer' => 'required|string',

        ]);

        // Retrieve the 'id' from the request input
        $id = $request->input('id');

        // Find the FAQ by ID
        $faq = Faq::find($id);

        // Update the FAQ fields
        $faq->question = $request->input('question');
        $faq->answer = $request->input('answer');
        $faq->save();

        // Return the updated FAQ in the response
        return response()->json([
            'status_code' => 1,
            'data' => ['faq' => $faq],
            'message' => 'FAQ updated successfully.'
        ]);
    }

    public function deleteFaq($id)
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json([
                'status_code' => 2,
                'message' => 'FAQ not found.'
            ]);
        }

        $faq->status = 'INACTIVE';
        $faq->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'Deleted successfully.'
        ]);
    }
}
